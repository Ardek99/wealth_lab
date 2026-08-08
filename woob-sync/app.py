import os
import traceback
from datetime import date, datetime

from flask import Flask, jsonify, request
from woob.browser.exceptions import ClientError
from woob.capabilities.bank import Account, Transaction
from woob.capabilities.captcha import CaptchaQuestion
from woob.core import Woob
from woob.exceptions import (
    AppValidationError,
    BrowserIncorrectPassword,
    BrowserInteraction,
    ModuleLoadError,
    NeedInteractive,
    WrongCaptchaResponse,
)

app = Flask(__name__)

ACCOUNT_TYPE_MAP = {
    Account.TYPE_CHECKING: "checking",
    Account.TYPE_JOINT: "checking",
    Account.TYPE_CARD: "checking",
    Account.TYPE_SAVINGS: "savings",
    Account.TYPE_DEPOSIT: "savings",
    Account.TYPE_LIVRET_A: "savings",
    Account.TYPE_LIVRET_B: "savings",
    Account.TYPE_LDDS: "savings",
    Account.TYPE_PEL: "savings",
    Account.TYPE_CEL: "savings",
    Account.TYPE_CSL: "savings",
    Account.TYPE_RSP: "savings",
    Account.TYPE_LIFE_INSURANCE: "savings",
    Account.TYPE_CAT: "savings",
    Account.TYPE_LOAN: "credit",
    Account.TYPE_MORTGAGE: "credit",
    Account.TYPE_CONSUMER_CREDIT: "credit",
    Account.TYPE_REVOLVING_CREDIT: "credit",
    Account.TYPE_CROWDLENDING: "credit",
    Account.TYPE_MARKET: "investment",
    Account.TYPE_PEA: "investment",
    Account.TYPE_PEE: "investment",
    Account.TYPE_PERCO: "investment",
    Account.TYPE_PER: "investment",
    Account.TYPE_PERP: "investment",
    Account.TYPE_MADELIN: "investment",
    Account.TYPE_ARTICLE_83: "investment",
    Account.TYPE_CAPITALISATION: "investment",
    Account.TYPE_REAL_ESTATE: "investment",
}


def map_account_type(woob_type) -> str:
    return ACCOUNT_TYPE_MAP.get(woob_type, "checking")


def _patch_boursorama() -> None:
    """Boursorama serves a stub page first (sets the __brs_mit cookie via JS,
    then reloads) before the real login page. woob_modules.boursorama's
    PasswordPage.is_html_loaded() is supposed to detect that stub and let
    start_login() work around it, but it only checks for a <title> tag —
    and the stub page now has one too, so the check no longer distinguishes
    it from a real page. The stub's <body> is always empty, so check that
    instead. Patched here (rather than editing the vendored file) because
    woob re-downloads modules from its own repo and would overwrite it."""
    from woob.browser.filters.html import HasElement
    from woob_modules.boursorama.pages import PasswordPage

    PasswordPage.is_html_loaded = lambda self: HasElement("//body/*")(self.doc)

    # get_regular_transactions() always requests 3 years of history, hardcoded,
    # regardless of what the caller actually needs. For a checking account with
    # meaningful activity that means many pages of pagination, which is what
    # made syncs take several minutes (and repeatedly trip Symfony's HTTP idle
    # timeout). We only ever want `since` (BankSyncService.php requests the
    # last 3 months), so request that instead. Reads `browser.woob_sync_since`
    # (set in app.py right after the backend is loaded) and falls back to the
    # original 3-year window if it's not set.
    from datetime import date as _date

    from dateutil.relativedelta import relativedelta
    from woob_modules.boursorama.browser import BoursoramaBrowser

    def get_regular_transactions(self, account, coming):
        since = getattr(self, "woob_sync_since", None)
        from_date = since if since is not None else _date.today() - relativedelta(years=3)

        params = {}
        params["movementSearch[toDate]"] = (_date.today() + relativedelta(days=40)).strftime("%d/%m/%Y")
        params["movementSearch[fromDate]"] = from_date.strftime("%d/%m/%Y")
        params["movementSearch[advanced]"] = 1
        if self.otp_location("%s/mouvements" % account.url.rstrip("/"), params=params) is None:
            return

        json_config = self.page.get_json_config()
        for transaction in self.page.iter_history():
            if coming and transaction.coming and transaction.date > (_date.today() - relativedelta(days=30)):
                yield transaction
            if coming and not transaction.coming:
                break
            elif not coming and not transaction.coming:
                if transaction.id:
                    operation_detail_page = self.operation_detail.open(
                        user_hash=json_config["USER_HASH"], resource_id=transaction.id
                    )
                    operation_detail_page.fill_transaction(transaction)
                yield transaction

    BoursoramaBrowser.get_regular_transactions = get_regular_transactions


MODULE_PATCHES = {"boursorama": _patch_boursorama}
_patched_modules = set()


def apply_module_patches(module: str) -> None:
    if module in _patched_modules:
        return
    _patched_modules.add(module)
    patch_fn = MODULE_PATCHES.get(module)
    if patch_fn is None:
        return
    try:
        patch_fn()
    except ImportError:
        app.logger.warning("could not apply patch for module %s", module)


def dump_debug_page(backend, label: str) -> None:
    """When WOOB_SYNC_DEBUG=1, save the current browser page (URL + HTML) to
    /tmp so a scraping failure (e.g. a bank changed its login page) can be
    inspected locally. Off by default: once past login this is an
    authenticated bank page (real balances/transactions), so the file is
    written user-only (chmod 600) and should be deleted once inspected."""
    if os.environ.get("WOOB_SYNC_DEBUG") != "1":
        return
    try:
        page = backend.browser.page
        path = f"/tmp/woob_debug_{label}.html"
        fd = os.open(path, os.O_WRONLY | os.O_CREAT | os.O_TRUNC, 0o600)
        with os.fdopen(fd, "w", encoding="utf-8") as f:
            f.write(f"<!-- URL: {backend.browser.url} -->\n")
            f.write(page.text if hasattr(page, "text") else str(page.doc))
        app.logger.error("debug dump (user-only, delete after inspecting): url=%s saved to %s", backend.browser.url, path)
    except Exception:
        app.logger.error("debug dump failed: %s", traceback.format_exc())


def transaction_external_id(tr: Transaction) -> str:
    if tr.id:
        return str(tr.id)
    return f"{tr.raw}-{tr.date}-{tr.amount}"


def serialize_transaction(tr: Transaction) -> dict:
    tr_date = tr.rdate or tr.date
    return {
        "externalId": transaction_external_id(tr),
        "label": tr.label or tr.raw,
        "amount": float(tr.amount),
        "date": tr_date.isoformat() if hasattr(tr_date, "isoformat") else str(tr_date),
    }


def serialize_account(backend, account: Account, since: date) -> dict:
    transactions = []
    try:
        for tr in backend.iter_history(account):
            tr_date = tr.rdate or tr.date
            if hasattr(tr_date, "date"):
                tr_date = tr_date.date()
            if tr_date and tr_date < since:
                continue
            transactions.append(serialize_transaction(tr))
    except ClientError as e:
        # Boursorama (and possibly others) returns 401 when history pagination
        # goes further back than it allows without an extra OTP step. Treat it
        # as "no more data" for this account rather than failing the whole sync.
        if getattr(e.response, "status_code", None) != 401:
            raise
        app.logger.warning(
            "history fetch stopped early for account %s (401): keeping %d transactions",
            account.id,
            len(transactions),
        )
    except Exception as e:
        # Some account types (e.g. certain savings products) route through a
        # different page class than the common case and can break in ways
        # specific to that account. Don't let one account's history quirk
        # fail the whole sync — keep whatever we already gathered for it and
        # move on to the other accounts.
        app.logger.warning(
            "history fetch failed for account %s (%s): keeping %d transactions",
            account.id,
            type(e).__name__,
            len(transactions),
        )

    return {
        "externalId": str(account.id),
        "label": account.label,
        "balance": float(account.balance),
        "type": map_account_type(account.type),
        "transactions": transactions,
    }


@app.post("/sync")
def sync():
    payload = request.get_json(force=True, silent=True) or {}
    module = payload.get("module")
    config = payload.get("config") or {}
    since_raw = payload.get("since")

    if not module:
        return jsonify(status="error", message="Missing 'module'"), 400

    try:
        since = datetime.strptime(since_raw, "%Y-%m-%d").date() if since_raw else date.min
    except ValueError:
        return jsonify(status="error", message="Invalid 'since' date, expected YYYY-MM-DD"), 400

    w = Woob()
    try:
        try:
            backend = w.load_backend(module, "sync", params=config)
        except ModuleLoadError as e:
            return jsonify(status="error", message=str(e))

        apply_module_patches(module)
        backend.browser.woob_sync_since = since

        try:
            accounts = [
                serialize_account(backend, account, since)
                for account in backend.iter_accounts()
            ]
        except BrowserIncorrectPassword as e:
            app.logger.warning("invalid_credentials: %s", traceback.format_exc())
            return jsonify(status="invalid_credentials", message=str(e) or type(e).__name__)
        except (CaptchaQuestion, WrongCaptchaResponse) as e:
            app.logger.warning("captcha_required: %s", traceback.format_exc())
            return jsonify(status="captcha_required", message=str(e) or type(e).__name__)
        except (BrowserInteraction, NeedInteractive, AppValidationError) as e:
            app.logger.warning("2fa_required: %s", traceback.format_exc())
            return jsonify(status="2fa_required", message=str(e) or type(e).__name__)
        except Exception as e:
            app.logger.error("sync error: %s", traceback.format_exc())
            dump_debug_page(backend, "sync_failure")
            return jsonify(status="error", message=str(e) or type(e).__name__)

        return jsonify(accounts=accounts)
    finally:
        w.deinit()


if __name__ == "__main__":
    app.run(host="127.0.0.1", port=8002)
