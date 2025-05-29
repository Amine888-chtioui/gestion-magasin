<?php
// app/Models/Machine.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Machine extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom',
        'numero_serie',
        'modele',
        'description',
        'localisation',
        'statut',
        'date_installation',
        'derniere_maintenance',
        'specifications_techniques',
    ];

    protected $casts = [
        'date_installation' => 'date',
        'derniere_maintenance' => 'date',
        'specifications_techniques' => 'array',
    ];

    // Relations
    public function composants()
    {
        return $this->hasMany(Composant::class);
    }

    public function demandes()
    {
        return $this->hasMany(Demande::class);
    }

    // Scopes
    public function scopeActives($query)
    {
        return $query->where('statut', 'actif');
    }

    public function scopeParLocalisation($query, $localisation)
    {
        return $query->where('localisation', 'like', "%{$localisation}%");
    }

    public function scopeParModele($query, $modele)
    {
        return $query->where('modele', 'like', "%{$modele}%");
    }

    public function scopeNecessiteMaintenace($query)
    {
        return $query->where('derniere_maintenance', '<', Carbon::now()->subMonths(6));
    }

    // Accesseurs
    public function getNombreComposantsAttribute()
    {
        return $this->composants()->count();
    }

    public function getComposantsDefaillants()
    {
        return $this->composants()->where('statut', 'defaillant')->count();
    }

    public function getTempsDepuisMaintenanceAttribute()
    {
        if (!$this->derniere_maintenance) {
            return null;
        }
        return $this->derniere_maintenance->diffInDays(Carbon::now());
    }

    public function getStatutMaintenanceAttribute()
    {
        $jours = $this->temps_depuis_maintenance;
        if ($jours === null) return 'non_defini';
        if ($jours > 180) return 'critique';
        if ($jours > 120) return 'attention';
        return 'ok';
    }
}