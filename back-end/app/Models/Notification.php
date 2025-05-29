<?php
// app/Models/Notification.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'titre',
        'message',
        'type',
        'data',
        'lu',
        'lu_le',
    ];

    protected $casts = [
        'data' => 'array',
        'lu' => 'boolean',
        'lu_le' => 'datetime',
    ];

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeNonLues($query)
    {
        return $query->where('lu', false);
    }

    public function scopeLues($query)
    {
        return $query->where('lu', true);
    }

    public function scopeParType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeParUtilisateur($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeRecentes($query, $jours = 7)
    {
        return $query->where('created_at', '>', now()->subDays($jours));
    }

    // Accesseurs
    public function getTypeColorAttribute()
    {
        return match($this->type) {
            'info' => 'primary',
            'success' => 'success',
            'warning' => 'warning',
            'error' => 'danger',
            default => 'secondary'
        };
    }

    public function getTypeIconAttribute()
    {
        return match($this->type) {
            'info' => 'fas fa-info-circle',
            'success' => 'fas fa-check-circle',
            'warning' => 'fas fa-exclamation-triangle',
            'error' => 'fas fa-times-circle',
            default => 'fas fa-bell'
        };
    }

    public function getTempsEcouleAttribute()
    {
        return $this->created_at->diffForHumans();
    }

    // Méthodes
    public function marquerCommeLue()
    {
        if (!$this->lu) {
            $this->update([
                'lu' => true,
                'lu_le' => now(),
            ]);
        }
    }

    public function marquerCommeNonLue()
    {
        $this->update([
            'lu' => false,
            'lu_le' => null,
        ]);
    }

    // Méthodes statiques pour créer des notifications
    public static function creerNotification($userId, $titre, $message, $type = 'info', $data = null)
    {
        return static::create([
            'user_id' => $userId,
            'titre' => $titre,
            'message' => $message,
            'type' => $type,
            'data' => $data,
        ]);
    }

    public static function notifierNouvelleDemandeAdmin($demande)
    {
        $admins = User::where('role', 'admin')->get();
        
        foreach ($admins as $admin) {
            static::creerNotification(
                $admin->id,
                'Nouvelle demande',
                "Une nouvelle demande #{$demande->numero_demande} a été soumise par {$demande->user->name}",
                'info',
                ['demande_id' => $demande->id, 'type' => 'nouvelle_demande']
            );
        }
    }

    public static function notifierStatutDemande($demande)
    {
        $message = match($demande->statut) {
            'acceptee' => "Votre demande #{$demande->numero_demande} a été acceptée",
            'refusee' => "Votre demande #{$demande->numero_demande} a été refusée",
            'en_cours' => "Votre demande #{$demande->numero_demande} est en cours de traitement",
            'terminee' => "Votre demande #{$demande->numero_demande} a été terminée",
            default => "Le statut de votre demande #{$demande->numero_demande} a été mis à jour"
        };

        $type = match($demande->statut) {
            'acceptee' => 'success',
            'refusee' => 'error',
            'en_cours' => 'info',
            'terminee' => 'success',
            default => 'info'
        };

        static::creerNotification(
            $demande->user_id,
            'Mise à jour de demande',
            $message,
            $type,
            ['demande_id' => $demande->id, 'type' => 'statut_demande']
        );
    }

    public static function notifierMaintenanceMachine($machine)
    {
        $admins = User::where('role', 'admin')->get();
        
        foreach ($admins as $admin) {
            static::creerNotification(
                $admin->id,
                'Maintenance requise',
                "La machine {$machine->nom} nécessite une maintenance",
                'warning',
                ['machine_id' => $machine->id, 'type' => 'maintenance_requise']
            );
        }
    }

    public static function notifierComposantDefaillant($composant)
    {
        $admins = User::where('role', 'admin')->get();
        
        foreach ($admins as $admin) {
            static::creerNotification(
                $admin->id,
                'Composant défaillant',
                "Le composant {$composant->nom} de la machine {$composant->machine->nom} est défaillant",
                'error',
                ['composant_id' => $composant->id, 'machine_id' => $composant->machine_id, 'type' => 'composant_defaillant']
            );
        }
    }
}