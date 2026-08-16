<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class MaterialTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            // `code` is varchar(30): slug(2) can exceed that with two long words,
            // which failed at random on Postgres (sqlite does not enforce the
            // length). A bounded pattern keeps it unique and always in range.
            'code' => fake()->unique()->bothify('MT-####-????'),
            'name' => fake()->words(2, true),
        ];
    }
}
