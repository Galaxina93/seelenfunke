<?php

namespace crud;

class RightsConfig
{

    public static function getConfig(): array
    {

        return [

            // Definiere das Model, welches geladen werden soll.
            'model' => 'Role',

            // Definiere den Titel für das CRUD
            'crud_title' => 'Rechteverwaltung',

            // Definiere den Namen des Erstellbuttons
            'create_btn_name' => 'Rolle erstellen',

            // Setze die Anzahl an Datensätzen pro Seite
            'per_page' => 10,

            // Definiere die Felder, die sortiert werden können und lege die Richtung fest.
            'sort_direction' => 'asc',
            'sortable' => ['name'],

            // Definiere die Felder, die gesucht werden können
            'searchable' => ['name'],

            // Lege die Berechtigungen fest
            'permissions' => [
                'edit'          => 'manage_roles',
                'delete'        => 'manage_roles',
                'force_delete'  => 'manage_roles',
                'archive'       => 'manage_roles',
            ],

            // Erweitere das Crud mit Beziehungen
            'with' => [
                'permissions'
            ],

            // Definiere die Felder die genutzt werden sollen
            'fields' => [

                'id' => [
                    'hide_on_index'     => false,
                    'hide_on_update'    => false,
                    'hide_on_create'    => false,
                    'required'          => false,
                    'type'              => 'id',
                    'translation'       => 'ID',
                    'placeholder'       => 'ID',
                    'rules'             => '',
                    'class'             => 'col-span-full md:col-span-3'
                ],

                'name' => [
                    'hide_on_index'     => false,
                    'hide_on_update'    => false,
                    'hide_on_create'    => false,
                    'required'          => false,
                    'type'              => 'text',
                    'translation'       => 'Rolle',
                    'placeholder'       => 'Rolle',
                    'rules'             => '',
                    'class'             => 'col-span-full md:col-span-3'
                ],

                'permissions' => [
                    'hide_on_index'     => true,
                    'hide_on_update'    => false,
                    'hide_on_create'    => true,
                    'required'          => false,
                    'data'              => [
                                                'permissions'       => [],
                                                'roles'             => [],
                                           ],
                    'type'              => 'roleGrid',
                    'translation'       => 'Rechte',
                    'placeholder'       => '',
                    'rules'             => '',
                    'class'             => 'col-span-full'
                ]

            ],

        ];

    }

}
