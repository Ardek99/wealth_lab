<?php

namespace App\DTO;
use Symfony\Component\Validator\Constraints as Assert;

class UserDTO
{
    public function __construct(
        #[Assert\NotBlank]
        public string $firstName = '',
        #[Assert\NotBlank]
        public string $lastName = '',
        #[Assert\NotBlank]
        #[Assert\Email]
        public string $email = '',
        #[Assert\NotBlank]
        #[Assert\Regex(
            pattern: '/^0[1-9](\d{8})$/',
            message: 'Phone number must be in E.164 format (e.g. +1234567890).'
        )]
        public string $phoneNumber = '',
        #[Assert\NotBlank]
        #[Assert\Regex(
            pattern: '/^(?=.*[A-Za-z])(?=.*\d)(?=.*[\W_]).{8,}$/',
            message: 'Password must be at least 8 characters, with letters, numbers and a special character.'
        )]
        public string $password = '',
    ) {
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function getName(): ?string
    {
        return $this->firstName . ' ' . $this->lastName;
    }
    
    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }
}