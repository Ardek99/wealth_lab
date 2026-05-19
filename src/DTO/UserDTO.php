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
        public string $password = '',
    ) {
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
}