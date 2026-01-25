<?php

namespace crud;

class EmployeeConfig
{

    public static function getConfig(): array
    {

        return [

            // Definiere das Model, welches hier genutzt wird.
            'model' => 'Employee',

            // Definiere den Titel für das CRUD
            'crud_title' => 'Mitarbeiter',

            // Definiere den Namen des Erstellbuttons
            'create_btn_name' => 'Mitarbeiter erstellen',

            // Setze die Anzahl an Datensätzen pro Seite
            'per_page' => 10,

            // Definiere die Felder, die sortiert werden können und lege die Richtung fest.
            'sort_direction' => 'asc',
            'sortable' => ['first_name', 'last_name', 'email'],

            // Definiere die Felder, die gesucht werden können
            'searchable' => ['first_name', 'last_name', 'email'],

            // Lege die Berechtigungen fest
            'permissions' => [
                'create'        => 'manage_employees',
                'edit'          => 'manage_employees',
                'delete'        => 'manage_employees',
                'force_delete'  => 'manage_employees',
                'archive'       => 'manage_employees',
            ],

            // Erweitere das Crud mit Beziehungen
            'with' => [
                'profile'
            ],

            // Definiere die Felder die genutzt werden sollen
            'fields' => [

                'first_name' => [
                    'hide_on_index'     => false,
                    'hide_on_update'    => false,
                    'hide_on_create'    => false,
                    'required'          => true,
                    'type'              => 'text',
                    'translation'       => 'Vorname',
                    'placeholder'       => 'Vorname',
                    'rules'             => 'required|string|max:255',
                    'class'             => 'col-span-full md:col-span-3'
                ],
                'last_name' => [
                    'hide_on_index'     => false,
                    'hide_on_update'    => false,
                    'hide_on_create'    => false,
                    'required'          => true,
                    'type'              => 'text',
                    'translation'       => 'Nachname',
                    'placeholder'       => 'Nachname',
                    'rules'             => 'required|string|max:255',
                    'class'             => 'col-span-full md:col-span-3'
                ],
                'email' => [
                    'hide_on_index'     => false,
                    'hide_on_update'    => false,
                    'hide_on_create'    => false,
                    'required'          => true,
                    'type'              => 'email',
                    'translation'       => 'E-Mail',
                    'placeholder'       => 'E-Mail',
                    'rules'             => 'required|string|max:255',
                    'class'             => 'col-span-full'
                ],

                'url' => [
                    'hide_on_index'     => true,
                    'hide_on_update'    => false,
                    'hide_on_create'    => true,
                    'required'          => false,
                    'type'              => 'text',
                    'translation'       => 'URL',
                    'placeholder'       => 'URL',
                    'rules'             => '',
                    'relation'          => 'profile',
                    'class'             => 'col-span-full md:col-span-3'
                ],
                'phone_number' => [
                    'hide_on_index'     => false,
                    'hide_on_update'    => false,
                    'hide_on_create'    => true,
                    'required'          => false,
                    'type'              => 'text',
                    'translation'       => 'Telefon',
                    'placeholder'       => 'Telefon',
                    'rules'             => '',
                    'relation'          => 'profile',
                    'class'             => 'col-span-full md:col-span-3'
                ],

                'divider_1' => [
                    'hide_on_index'     => true,
                    'hide_on_create'    => true,
                    'translation'       => 'Adresse',
                    'type'              => 'divider_1',
                    'class'             => 'col-span-full font-bold py-4'
                ],

                'street' => [
                    'hide_on_index'     => false,
                    'hide_on_update'    => false,
                    'hide_on_create'    => true,
                    'required'          => false,
                    'type'              => 'text',
                    'translation'       => 'Straße',
                    'placeholder'       => 'Straße',
                    'rules'             => '',
                    'relation'          => 'profile',
                    'class'             => 'col-span-full md:col-span-4'
                ],
                'house_number' => [
                    'hide_on_index'     => false,
                    'hide_on_update'    => false,
                    'hide_on_create'    => true,
                    'required'          => false,
                    'type'              => 'text',
                    'translation'       => 'Haus Nr.',
                    'placeholder'       => 'Haus Nr.',
                    'rules'             => '',
                    'relation'          => 'profile',
                    'class'             => 'col-span-full md:col-span-2'
                ],
                'postal' => [
                    'hide_on_index'     => false,
                    'hide_on_update'    => false,
                    'hide_on_create'    => true,
                    'required'          => false,
                    'type'              => 'text',
                    'translation'       => 'Postleitzahl',
                    'placeholder'       => 'Postleitzahl',
                    'rules'             => '',
                    'relation'          => 'profile',
                    'class'             => 'col-span-full md:col-span-2'
                ],
                'city' => [
                    'hide_on_index'     => false,
                    'hide_on_update'    => false,
                    'hide_on_create'    => true,
                    'required'          => false,
                    'type'              => 'text',
                    'translation'       => 'Ort',
                    'placeholder'       => 'Ort',
                    'rules'             => '',
                    'relation'          => 'profile',
                    'class'             => 'col-span-full md:col-span-4'
                ],
                'about' => [
                    'hide_on_index'     => true,
                    'hide_on_update'    => false,
                    'hide_on_create'    => true,
                    'required'          => false,
                    'type'              => 'textarea',
                    'translation'       => 'Über',
                    'placeholder'       => 'Über',
                    'rules'             => '',
                    'relation'          => 'profile',
                    'class'             => 'col-span-full'
                ],
                'password' => [
                    'hide_on_index'     => true,
                    'hide_on_update'    => false,
                    'hide_on_create'    => false,
                    'required'          => true,
                    'type'              => 'password',
                    'translation'       => 'Passwort',
                    'placeholder'       => 'Passwort',
                    'rules'             => 'required|string|min:8',
                    'class'             => 'col-span-full md:col-span-2'
                ],

            ],

        ];

    }

}
