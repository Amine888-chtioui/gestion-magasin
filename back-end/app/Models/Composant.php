<?php
// app/Models/Composant.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Composant extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom',
        'reference',
        'machine_id',
        'type_id',
        'description',
        'statut',
        'quantite',
        'prix_unitaire',
        'fournisseur',
        'date_installation',
        'derniere_inspection',
        'prochaine_inspection',
        'duree_vie_estimee',
        'notes',
        'caracteristiques',
    ];

    protected $casts = [
        'date_installation' => 'date',
        'derniere_inspection' => 'date',
        'prochaine_inspection' => 'date',
        'prix_unitaire' => 'decimal:2',
        'quantite' => 'integer',
        'duree_vie_estimee' => 'integer',
        'caracteristiques' => 'array',
    ];

    // Relations
    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }

    public function type()
    {
        return $this->belongsTo(Type::class);
    }

    public function demandes()
    {
        return $this->hasMany(Demande::class);
    }

    // Scopes
    public function scopeParStatut($query, $statut)
    {
        return $query->where('statut', $statut);
    }

    public function scopeParType($query, $typeId)
    {
        return $query->where('type_id', $typeId);
    }

    public function scopeParMachine($query, $machineId)
    {
        return $query->where('machine_id', $machineId);
    }

    public function scopeDefaillants($query)
    {
        return $query->where('statut', 'defaillant');
    }

    public function scopeAInspecter($query)
    {
        return $query->where('prochaine_inspection', '<=', Carbon::now()->addDays(7));
    }

    public function scopeUsures($query)
    {
        return $query->where('statut', 'usure');
    }

    // Accesseurs
    public function getPrixTotalAttribute()
    {
        return $this->prix_unitaire * $this->quantite;
    }

    public function getAgeAttribute()
    {
        if (!$this->date_installation) {
            return null;
        }
        return $this->date_installation->diffInMonths(Carbon::now());
    }

    public function getStatutInspectionAttribute()
    {
        if (!$this->prochaine_inspection) {
            return 'non_programme';
        }
        
        $jours = Carbon::now()->diffInDays($this->prochaine_inspection, false);
        
        if ($jours < 0) return 'en_retard';
        if ($jours <= 7) return 'urgent';
        if ($jours <= 30) return 'proche';
        return 'ok';
    }

    public function getPourcentageVieAttribute()
    {
        if (!$this->duree_vie_estimee || !$this->date_installation) {
            return null;
        }
        
        $ageEnMois = $this->age;
        return min(100, ($ageEnMois / $this->duree_vie_estimee) * 100);
    }
}