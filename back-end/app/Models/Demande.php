<?php
// app/Models/Demande.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Demande extends Model
{
    use HasFactory;

    protected $fillable = [
        'numero_demande',
        'user_id',
        'machine_id',
        'composant_id',
        'type_demande',
        'priorite',
        'statut',
        'titre',
        'description',
        'justification',
        'quantite_demandee',
        'budget_estime',
        'date_souhaite',
        'traite_par',
        'commentaire_admin',
        'date_traitement',
    ];

    protected $casts = [
        'date_souhaite' => 'date',
        'date_traitement' => 'datetime',
        'budget_estime' => 'decimal:2',
        'quantite_demandee' => 'integer',
    ];

    // Boot method pour générer automatiquement le numéro de demande
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($demande) {
            if (empty($demande->numero_demande)) {
                $demande->numero_demande = static::genererNumeroDemande();
            }
        });
    }

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }

    public function composant()
    {
        return $this->belongsTo(Composant::class);
    }

    public function traitePar()
    {
        return $this->belongsTo(User::class, 'traite_par');
    }

    // Scopes
    public function scopeParStatut($query, $statut)
    {
        return $query->where('statut', $statut);
    }

    public function scopeParPriorite($query, $priorite)
    {
        return $query->where('priorite', $priorite);
    }

    public function scopeParType($query, $type)
    {
        return $query->where('type_demande', $type);
    }

    public function scopeEnAttente($query)
    {
        return $query->where('statut', 'en_attente');
    }

    public function scopeEnCours($query)
    {
        return $query->where('statut', 'en_cours');
    }

    public function scopeUrgentes($query)
    {
        return $query->whereIn('priorite', ['haute', 'critique']);
    }

    public function scopeParUtilisateur($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    // Méthodes statiques
    public static function genererNumeroDemande()
    {
        $prefix = 'DEM';
        $annee = date('Y');
        $mois = date('m');
        
        $derniere = static::where('numero_demande', 'like', "{$prefix}-{$annee}{$mois}-%")
            ->orderBy('numero_demande', 'desc')
            ->first();
        
        if ($derniere) {
            $dernierNumero = intval(substr($derniere->numero_demande, -4));
            $nouveauNumero = $dernierNumero + 1;
        } else {
            $nouveauNumero = 1;
        }
        
        return sprintf("%s-%s%s-%04d", $prefix, $annee, $mois, $nouveauNumero);
    }

    // Accesseurs
    public function getStatutColorAttribute()
    {
        return match($this->statut) {
            'en_attente' => 'warning',
            'en_cours' => 'info',
            'acceptee' => 'success',
            'refusee' => 'danger',
            'terminee' => 'secondary',
            default => 'light'
        };
    }

    public function getPrioriteColorAttribute()
    {
        return match($this->priorite) {
            'basse' => 'success',
            'normale' => 'info',
            'haute' => 'warning',
            'critique' => 'danger',
            default => 'light'
        };
    }

    public function getDelaiTraitementAttribute()
    {
        if (!$this->date_traitement) {
            return null;
        }
        return $this->created_at->diffInHours($this->date_traitement);
    }

    // Méthodes
    public function marquerCommeTraitee($admin, $commentaire = null)
    {
        $this->update([
            'traite_par' => $admin->id,
            'date_traitement' => now(),
            'commentaire_admin' => $commentaire,
        ]);
    }

    public function accepter($admin, $commentaire = null)
    {
        $this->update([
            'statut' => 'acceptee',
            'traite_par' => $admin->id,
            'date_traitement' => now(),
            'commentaire_admin' => $commentaire,
        ]);
    }

    public function refuser($admin, $commentaire = null)
    {
        $this->update([
            'statut' => 'refusee',
            'traite_par' => $admin->id,
            'date_traitement' => now(),
            'commentaire_admin' => $commentaire,
        ]);
    }
}