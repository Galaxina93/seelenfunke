<?php

namespace crud;

class AdminConfig
{

    public static function getConfig(): array
    {

        return [

            // Definiere das Model, welches hier genutzt wird.
            'model' => 'Admin',

            // Definiere den Titel für das CRUD
            'crud_title' => 'Administratoren',

            // Definiere den Namen des Erstellbuttons
            'create_btn_name' => 'Admin erstellen',

            // Setze die Anzahl an Datensätzen pro Seite
            'per_page' => 10,

            // Definiere die Felder, die sortiert werden können und lege die Richtung fest.
            'sort_direction' => 'asc',
            'sortable' => ['first_name', 'last_name', 'email'],

            // Definiere die Felder, die gesucht werden können
            'searchable' => ['first_name', 'last_name', 'email'],

            // Lege die Berechtigungen fest
            'permissions' => [
                'create'        => 'manage_admins',
                'edit'          => 'manage_admins',
                'delete'        => 'manage_admins',
                'force_delete'  => 'manage_admins',
                'archive'       => 'manage_admins',
            ],

            // Erweitere das Crud mit Beziehungen
            'with' => [
                'profile'
            ],

            // Definiere die Felder die genutzt werden sollen
            'fields' => [

                /*'profile' => [
                    'hide_on_index'     => true,
                    'hide_on_update'    => false,
                    'hide_on_create'    => false,
                    'required'          => false,
                    'type'              => 'select',
                    'translation'       => 'Profile',
                    'placeholder'       => 'Profile',
                    'rules'             => '',
                    'options'           => [],
                    'class'             => 'col-span-full'
                ],*/

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
                ],           // relation
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
                ],  // relation

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
                ],      // relation
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
                ],// relation
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
                ],      // relation
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
                ],        // relation

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
                ],         // relation
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
