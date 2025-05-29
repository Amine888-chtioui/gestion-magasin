<?php
// app/Models/Type.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Type extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom',
        'description',
        'couleur',
        'actif',
    ];

    protected $casts = [
        'actif' => 'boolean',
    ];

    // Relations
    public function composants()
    {
        return $this->hasMany(Composant::class);
    }

    // Scopes
    public function scopeActifs($query)
    {
        return $query->where('actif', true);
    }

    public function scopeParNom($query, $nom)
    {
        return $query->where('nom', 'like', "%{$nom}%");
    }

    // Accesseurs
    public function getNombreComposantsAttribute()
    {
        return $this->composants()->count();
    }

    public function getComposantsActifsAttribute()
    {
        return $this->composants()->whereIn('statut', ['bon', 'usure'])->count();
    }
}