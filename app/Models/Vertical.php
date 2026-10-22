<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Vertical extends Model {
    protected $fillable = ['name', 'slug', 'description', 'color', 'fields'];
    protected $casts = ['fields' => 'array'];
}
