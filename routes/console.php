<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('mongo:ensure-menu-schema', function () {
    $collectionName = 'menu_item';

    $validator = [
        '$jsonSchema' => [
            'bsonType' => 'object',
            'required' => ['name', 'price', 'category'],
            'properties' => [
                'name' => [
                    'bsonType' => 'string',
                    'description' => 'Menu name must be a string and is required',
                ],
                'description' => [
                    'bsonType' => ['string', 'null'],
                    'description' => 'Description must be a string or null',
                ],
                'price' => [
                    'bsonType' => ['double', 'int', 'long', 'decimal'],
                    'minimum' => 0,
                    'description' => 'Price must be a non-negative number and is required',
                ],
                'category' => [
                    'bsonType' => 'string',
                    'description' => 'Category must be a string and is required',
                ],
                'image_url' => [
                    'bsonType' => ['string', 'null'],
                    'description' => 'Image URL must be a string or null',
                ],
            ],
        ],
    ];

    try {
        $connection = DB::connection('mongodb');
        $database = $connection->getMongoDB();

        $collectionExists = false;
        foreach ($database->listCollections(['filter' => ['name' => $collectionName]]) as $collectionInfo) {
            if ($collectionInfo->getName() === $collectionName) {
                $collectionExists = true;
                break;
            }
        }

        if ($collectionExists) {
            $database->command([
                'collMod' => $collectionName,
                'validator' => $validator,
                'validationLevel' => 'moderate',
                'validationAction' => 'error',
            ]);

            $this->info('Mongo schema validator updated for collection: ' . $collectionName);
            return;
        }

        $database->createCollection($collectionName, [
            'validator' => $validator,
            'validationLevel' => 'moderate',
            'validationAction' => 'error',
        ]);

        $this->info('Mongo collection created with schema validator: ' . $collectionName);
    } catch (\Throwable $e) {
        $this->error('Failed to apply Mongo schema validator: ' . $e->getMessage());
    }
})->purpose('Create or update MongoDB schema validator for menu_item collection');
