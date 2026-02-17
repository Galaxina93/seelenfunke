<?php

// database/factories/NewsletterSubscriberFactory.php
namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class NewsletterSubscriberFactory extends Factory
{
    public function definition(): array
    {
        return [
            'email' => $this->faker->unique()->safeEmail(),
            'ip_address' => $this->faker->ipv4(),
            'privacy_accepted' => true,
            'is_verified' => false,
            'verification_token' => Str::random(32),
        ];
    }
}
