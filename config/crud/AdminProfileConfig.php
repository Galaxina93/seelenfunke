<?php

namespace crud;

class AdminProfileConfig
{

    public static function getConfig(): array
    {

        return [

            // Definiere das Model, welches hier genutzt wird.
            'model' => 'AdminProfile',

            // Definiere den Titel für das CRUD
            'crud_title' => 'Admin Profil',

            // Definiere den Namen des Erstellbuttons
            'create_btn_name' => 'Profile erstellen',

            // Setze die Anzahl an Datensätzen pro Seite
            'per_page' => 10,

            // Definiere die Felder, die sortiert werden können und lege die Richtung fest.
            'sort_direction' => 'asc',
            'sortable' => ['id', 'admin_id', 'phone_number', 'city', 'postal', 'street', 'house_number'],

            // Definiere die Felder, die gesucht werden können
            'searchable' => ['id', 'admin_id', 'phone_number', 'city', 'postal', 'street', 'house_number'],

            // Lege die Berechtigungen fest
            'permissions' => [
                'create'        => 'manage_admin_profiles',
                'edit'          => 'manage_admin_profiles',
                'delete'        => 'manage_admin_profiles',
                'force_delete'  => 'manage_admin_profiles',
                'archive'       => 'manage_admin_profiles',
            ],

            // Erweitere das Crud mit Beziehungen
            'with' => [
                'admin'
            ],

            // Definiere die Felder die genutzt werden sollen
            'fields' => [
                'id' => [
                    'hide_on_index'     => true,
                    'hide_on_update'    => false,
                    'hide_on_create'    => false,
                    'required'          => false,
                    'type'              => 'text',
                    'translation'       => 'ID',
                    'placeholder'       => 'ID',
                    'rules'             => '',
                    'class'             => 'col-span-full md:col-span-3'
                ],
                'admin_id' => [
                    'hide_on_index'     => false,
                    'hide_on_update'    => false,
                    'hide_on_create'    => false,
                    'required'          => false,
                    'type'              => 'text',
                    'translation'       => 'Admin (id)',
                    'placeholder'       => 'Admin ID',
                    'rules'             => '',
                    'class'             => 'col-span-full md:col-span-3',
                    'closure_function'  => 'Crud\AdminProfileConfig@getNameByAdminId'
                ],
                'photo_path' => [
                    'hide_on_index'     => true,
                    'hide_on_update'    => false,
                    'hide_on_create'    => false,
                    'required'          => false,
                    'type'              => 'text',
                    'translation'       => 'Foto Pfad',
                    'placeholder'       => 'Foto Pfad',
                    'rules'             => '',
                    'class'             => 'col-span-full'
                ],
                'about' => [
                    'hide_on_index'     => true,
                    'hide_on_update'    => false,
                    'hide_on_create'    => false,
                    'required'          => false,
                    'type'              => 'textarea',
                    'translation'       => 'Über',
                    'placeholder'       => 'Über',
                    'rules'             => '',
                    'class'             => 'col-span-full'
                ],
                'url' => [
                    'hide_on_index'     => true,
                    'hide_on_update'    => false,
                    'hide_on_create'    => false,
                    'required'          => false,
                    'type'              => 'text',
                    'translation'       => 'URL',
                    'placeholder'       => 'URL',
                    'rules'             => '',
                    'class'             => 'col-span-full md:col-span-3'
                ],
                'phone_number' => [
                    'hide_on_index'     => false,
                    'hide_on_update'    => false,
                    'hide_on_create'    => false,
                    'required'          => false,
                    'type'              => 'text',
                    'translation'       => 'Telefonnummer',
                    'placeholder'       => 'Telefonnummer',
                    'rules'             => '',
                    'class'             => 'col-span-full md:col-span-3'
                ],
                'street' => [
                    'hide_on_index'     => false,
                    'hide_on_update'    => false,
                    'hide_on_create'    => false,
                    'required'          => false,
                    'type'              => 'text',
                    'translation'       => 'Straße',
                    'placeholder'       => 'Straße',
                    'rules'             => '',
                    'class'             => 'col-span-full md:col-span-4'
                ],
                'house_number' => [
                    'hide_on_index'     => false,
                    'hide_on_update'    => false,
                    'hide_on_create'    => false,
                    'required'          => false,
                    'type'              => 'text',
                    'translation'       => 'Hausnummer',
                    'placeholder'       => 'Hausnummer',
                    'rules'             => '',
                    'class'             => 'col-span-full md:col-span-2'
                ],
                'postal' => [
                    'hide_on_index'     => false,
                    'hide_on_update'    => false,
                    'hide_on_create'    => false,
                    'required'          => false,
                    'type'              => 'text',
                    'translation'       => 'Postleitzahl',
                    'placeholder'       => 'Postleitzahl',
                    'rules'             => '',
                    'class'             => 'col-span-full md:col-span-2'
                ],
                'city' => [
                    'hide_on_index'     => false,
                    'hide_on_update'    => false,
                    'hide_on_create'    => false,
                    'required'          => false,
                    'type'              => 'text',
                    'translation'       => 'Stadt',
                    'placeholder'       => 'Stadt',
                    'rules'             => '',
                    'class'             => 'col-span-full md:col-span-4'
                ],
                'two_factor_is_active' => [
                    'hide_on_index'     => true,
                    'hide_on_update'    => false,
                    'hide_on_create'    => false,
                    'required'          => false,
                    'type'              => 'checkbox',
                    'translation'       => 'Zwei-Faktor-Authentifizierung aktiv',
                    'placeholder'       => 'Zwei-Faktor-Authentifizierung aktiv',
                    'rules'             => '',
                    'class'             => 'col-span-full md:col-span-2'
                ],
                'two_factor_secret' => [
                    'hide_on_index'     => true,
                    'hide_on_update'    => false,
                    'hide_on_create'    => false,
                    'required'          => false,
                    'type'              => 'text',
                    'translation'       => 'Zwei-Faktor-Geheimnis',
                    'placeholder'       => 'Zwei-Faktor-Geheimnis',
                    'rules'             => '',
                    'class'             => 'col-span-full md:col-span-2'
                ],
                'two_factor_recovery_codes' => [
                    'hide_on_index'     => true,
                    'hide_on_update'    => false,
                    'hide_on_create'    => false,
                    'required'          => false,
                    'type'              => 'text',
                    'translation'       => 'Zwei-Faktor-Wiederherstellungscodes',
                    'placeholder'       => 'Zwei-Faktor-Wiederherstellungscodes',
                    'rules'             => '',
                    'class'             => 'col-span-full md:col-span-2'
                ],
                'email_verified_at' => [
                    'hide_on_index'     => true,
                    'hide_on_update'    => false,
                    'hide_on_create'    => false,
                    'required'          => false,
                    'type'              => 'date',
                    'translation'       => 'E-Mail verifiziert am',
                    'placeholder'       => 'E-Mail verifiziert am',
                    'rules'             => '',
                    'class'             => 'col-span-full md:col-span-3'
                ],
                'last_seen' => [
                    'hide_on_index'     => true,
                    'hide_on_update'    => false,
                    'hide_on_create'    => false,
                    'required'          => false,
                    'type'              => 'date',
                    'translation'       => 'Zuletzt gesehen',
                    'placeholder'       => 'Zuletzt gesehen',
                    'rules'             => '',
                    'class'             => 'col-span-full md:col-span-3'
                ],
                'deleted_at' => [
                    'hide_on_index'     => true,
                    'hide_on_update'    => true,
                    'hide_on_create'    => true,
                    'required'          => false,
                    'type'              => 'date',
                    'translation'       => 'Gelöscht am',
                    'placeholder'       => 'Gelöscht am',
                    'rules'             => '',
                    'class'             => 'col-span-full md:col-span-2'
                ],
                'created_at' => [
                    'hide_on_index'     => true,
                    'hide_on_update'    => true,
                    'hide_on_create'    => true,
                    'required'          => false,
                    'type'              => 'date',
                    'translation'       => 'Erstellt am',
                    'placeholder'       => 'Erstellt am',
                    'rules'             => '',
                    'class'             => 'col-span-full md:col-span-2'
                ],
                'updated_at' => [
                    'hide_on_index'     => true,
                    'hide_on_update'    => true,
                    'hide_on_create'    => true,
                    'required'          => false,
                    'type'              => 'date',
                    'translation'       => 'Aktualisiert am',
                    'placeholder'       => 'Aktualisiert am',
                    'rules'             => '',
                    'class'             => 'col-span-full md:col-span-2'
                ],
            ],

        ];

    }

    /* Closure Functions */
    public static function getNameByAdminId($row)
    {
        return $row->admin->first_name;
    }
}
