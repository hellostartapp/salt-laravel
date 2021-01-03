<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\Model;
use DB;
use Illuminate\Support\Facades\Schema;

class Countries extends Resources {

    protected $rules = array(
        'name' => 'required|string',
        'isocode' => 'required|string|max:2|unique:countries',
        'phonecode' => 'required|integer|unique:countries'
    );

    // "type" => "bigint"
    // "length" => 20
    // "spec_in_brackets" => "20"
    // "enum_set_values" => []
    // "print_type" => "bigint(20)"
    // "binary" => false
    // "unsigned" => true
    // "zerofill" => false
    // "attribute" => "UNSIGNED"
    // "can_contain_collation" => false
    // "displayed_type" => "bigint(20)"
    // "value" => null
    // "field" => "id"
    // "null" => "NO"
    // "key" => "PRI"
    // "default" => null
    // "extra" => "auto_increment"
    // "display" => false

    protected $structures = array(
        "id" => [
            'name' => 'id',
            'default' => null,
            'label' => 'ID',
            'display' => false,
            'validation' => [
                'create' => null,
                'update' => null,
                'delete' => null,
            ],
            'primary' => true,
            'required' => true,
            'type' => 'integer',
            'validated' => false,
            'nullable' => false,
            'note' => null
        ],
        "name" => [
            'name' => 'name',
            'default' => null,
            'label' => 'Name',
            'display' => true,
            'validation' => [
                'create' => 'required|string',
                'update' => 'required|string',
                'delete' => null,
            ],
            'primary' => false,
            'required' => true,
            'type' => 'text',
            'validated' => true,
            'nullable' => false,
            'note' => null
        ],
        "isocode" => [
            'name' => 'isocode',
            'default' => null,
            'label' => 'ISO Code',
            'display' => true,
            'validation' => [
                'create' => 'required|string|max:2|unique:countries',
                'update' => 'required|string|max:2|unique:countries,isocode,{id}',
                'delete' => null,
            ],
            'primary' => false,
            'required' => true,
            'type' => 'text',
            'validated' => true,
            'nullable' => false,
            'note' => null
        ],
        "phonecode" => [
            'name' => 'phonecode',
            'default' => null,
            'label' => 'Phone Code',
            'display' => true,
            'validation' => [
                'create' => 'required|integer|unique:countries',
                'update' => 'required|integer|unique:countries,phonecode,{id}',
                'delete' => null,
            ],
            'primary' => false,
            'required' => true,
            'type' => 'text',
            'validated' => true,
            'nullable' => false,
            'note' => null
        ],
        "created_at" => [
            'name' => 'created_at',
            'default' => null,
            'label' => 'Created At',
            'display' => false,
            'validation' => [
                'create' => null,
                'update' => null,
                'delete' => null,
            ],
            'primary' => false,
            'required' => false,
            'type' => 'datetime',
            'validated' => false,
            'nullable' => false,
            'note' => null
        ],
        "updated_at" => [
            'name' => 'updated_at',
            'default' => null,
            'label' => 'Updated At',
            'display' => false,
            'validation' => [
                'create' => null,
                'update' => null,
                'delete' => null,
            ],
            'primary' => false,
            'required' => false,
            'type' => 'datetime',
            'validated' => false,
            'nullable' => false,
            'note' => null
        ],
        "deleted_at" => [
            'name' => 'deleted_at',
            'default' => null,
            'label' => 'Deleted At',
            'display' => false,
            'validation' => [
                'create' => null,
                'update' => null,
                'delete' => null,
            ],
            'primary' => false,
            'required' => false,
            'type' => 'datetime',
            'validated' => false,
            'nullable' => false,
            'note' => null
        ]
    );

    protected $searchable = array('name', 'isocode', 'phonecode');

    public function provinces() {
        return $this->hasMany('App\Models\Provinces', 'country_id', 'id');
    }

    public function cities() {
        return $this->hasMany('App\Models\Cities', 'country_id', 'id');
    }
}
