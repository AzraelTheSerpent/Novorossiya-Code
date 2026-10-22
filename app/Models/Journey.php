<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Journey extends Model {
    protected $fillable = ['user_id', 'name', 'slug', 'description', 'duration', 'published'];
    protected $casts = ['published' => 'boolean'];
    public function places() { return $this->belongsToMany(Place::class)->withPivot(['position', 'minutes'])->orderBy('pivot_position'); }
}
