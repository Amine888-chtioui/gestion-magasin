<?php
// app/Http/Controllers/Api/DemandeController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Demande;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DemandeController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Demande::with(['user', 'machine', 'composant', 'traitePar']);

            // Filtres de rôle
            $user = $request->user();
            if (!$user->isAdmin()) {
                $query->where('user_id', $user->id);
            }

            // Filtres généraux
            if ($request->has('statut')) {
                $query->where('statut', $request->statut);
            }

            if ($request->has('type_demande')) {
                $query->where('type_demande', $request->type_demande);
            }

            if ($request->has('priorite')) {
                $query->where('priorite', $request->priorite);
            }

            if ($request->has('machine_id')) {
                $query->where('machine_id', $request->machine_id);
            }

            if ($request->has('user_id') && $user->isAdmin()) {
                $query->where('user_id', $request->user_id);
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('numero_demande', 'like', "%{$search}%")
                      ->orWhere('titre', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            // Filtres de date
            if ($request->has('date_debut')) {
                $query->whereDate('created_at', '>=', $request->date_debut);
            }

            if ($request->has('date_fin')) {
                $query->whereDate('created_at', '<=', $request->date_fin);
            }

            // Filtres spéciaux
            if ($request->has('urgentes') && $request->boolean('urgentes')) {
                $query->urgentes();
            }

            if ($request->has('en_attente') && $request->boolean('en_attente')) {
                $query->enAttente();
            }

            // Tri
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = $request->get('per_page', 15);
            $demandes = $query->paginate($perPage);

            // Ajouter des données calculées
            $demandes->getCollection()->transform(function ($demande) {
                $demande->append(['statut_color', 'priorite_color', 'delai_traitement']);
                return $demande;
            });

            return response()->json([
                'message' => 'Demandes récupérées avec succès',
                'data' => $demandes
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des demandes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'machine_id' => 'required|exists:machines,id',
                'composant_id' => 'nullable|exists:composants,id',
                'type_demande' => 'required|in:maintenance,piece,reparation,inspection',
                'priorite' => 'sometimes|in:basse,normale,haute,critique',
                'titre' => 'required|string|max:150',
                'description' => 'required|string',
                'justification' => 'nullable|string',
                'quantite_demandee' => 'nullable|integer|min:1',
                'budget_estime' => 'nullable|numeric|min:0',
                'date_souhaite' => 'nullable|date|after:today'
            ]);

            $data = $request->all();
            $data['user_id'] = $request->user()->id;

            $demande = Demande::create($data);
            $demande->load(['user', 'machine', 'composant']);

            // Notifier les administrateurs
            Notification::notifierNouvelleDemandeAdmin($demande);

            return response()->json([
                'message' => 'Demande créée avec succès',
                'data' => $demande
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la création de la demande',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $user = request()->user();
            $query = Demande::with(['user', 'machine', 'composant', 'traitePar']);
            
            // Vérification des permissions
            if (!$user->isAdmin()) {
                $query->where('user_id', $user->id);
            }

            $demande = $query->findOrFail($id);
            $demande->append(['statut_color', 'priorite_color', 'delai_traitement']);

            return response()->json([
                'message' => 'Demande récupérée avec succès',
                'data' => $demande
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Demande non trouvée',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $user = $request->user();
            $query = Demande::query();

            // Seuls les utilisateurs peuvent modifier leurs propres demandes (si en attente)
            // Les admins peuvent modifier toutes les demandes
            if (!$user->isAdmin()) {
                $query->where('user_id', $user->id);
            }

            $demande = $query->findOrFail($id);

            // Un utilisateur ne peut modifier que ses demandes en attente
            if (!$user->isAdmin() && $demande->statut !== 'en_attente') {
                return response()->json([
                    'message' => 'Vous ne pouvez pas modifier cette demande'
                ], 403);
            }

            $rules = [
                'titre' => 'sometimes|string|max:150',
                'description' => 'sometimes|string',
                'justification' => 'nullable|string',
                'quantite_demandee' => 'nullable|integer|min:1',
                'budget_estime' => 'nullable|numeric|min:0',
                'date_souhaite' => 'nullable|date|after:today'
            ];

            // Les admins peuvent modifier des champs supplémentaires
            if ($user->isAdmin()) {
                $rules = array_merge($rules, [
                    'priorite' => 'sometimes|in:basse,normale,haute,critique',
                    'statut' => 'sometimes|in:en_attente,en_cours,acceptee,refusee,terminee',
                    'commentaire_admin' => 'nullable|string'
                ]);
            }

            $request->validate($rules);

            $ancienStatut = $demande->statut;
            $demande->update($request->all());

            // Si changement de statut par admin, notifier l'utilisateur
            if ($user->isAdmin() && $request->has('statut') && $request->statut !== $ancienStatut) {
                $demande->marquerCommeTraitee($user, $request->commentaire_admin);
                Notification::notifierStatutDemande($demande);
            }

            $demande->load(['user', 'machine', 'composant', 'traitePar']);

            return response()->json([
                'message' => 'Demande mise à jour avec succès',
                'data' => $demande
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la mise à jour de la demande',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $user = request()->user();
            $query = Demande::query();

            if (!$user->isAdmin()) {
                $query->where('user_id', $user->id);
            }

            $demande = $query->findOrFail($id);

            // Un utilisateur ne peut supprimer que ses demandes en attente
            if (!$user->isAdmin() && $demande->statut !== 'en_attente') {
                return response()->json([
                    'message' => 'Vous ne pouvez pas supprimer cette demande'
                ], 403);
            }

            $demande->delete();

            return response()->json([
                'message' => 'Demande supprimée avec succès'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la suppression de la demande',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function accepter(Request $request, $id)
    {
        try {
            $user = $request->user();

            if (!$user->isAdmin()) {
                return response()->json([
                    'message' => 'Action non autorisée'
                ], 403);
            }

            $request->validate([
                'commentaire_admin' => 'nullable|string'
            ]);

            $demande = Demande::findOrFail($id);

            if ($demande->statut !== 'en_attente') {
                return response()->json([
                    'message' => 'Cette demande ne peut plus être acceptée'
                ], 400);
            }

            $demande->accepter($user, $request->commentaire_admin);
            Notification::notifierStatutDemande($demande);

            $demande->load(['user', 'machine', 'composant', 'traitePar']);

            return response()->json([
                'message' => 'Demande acceptée avec succès',
                'data' => $demande
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de l\'acceptation de la demande',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function refuser(Request $request, $id)
    {
        try {
            $user = $request->user();

            if (!$user->isAdmin()) {
                return response()->json([
                    'message' => 'Action non autorisée'
                ], 403);
            }

            $request->validate([
                'commentaire_admin' => 'required|string'
            ]);

            $demande = Demande::findOrFail($id);

            if ($demande->statut !== 'en_attente') {
                return response()->json([
                    'message' => 'Cette demande ne peut plus être refusée'
                ], 400);
            }

            $demande->refuser($user, $request->commentaire_admin);
            Notification::notifierStatutDemande($demande);

            $demande->load(['user', 'machine', 'composant', 'traitePar']);

            return response()->json([
                'message' => 'Demande refusée avec succès',
                'data' => $demande
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors du refus de la demande',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function changerStatut(Request $request, $id)
    {
        try {
            $user = $request->user();

            if (!$user->isAdmin()) {
                return response()->json([
                    'message' => 'Action non autorisée'
                ], 403);
            }

            $request->validate([
                'statut' => 'required|in:en_attente,en_cours,acceptee,refusee,terminee',
                'commentaire_admin' => 'nullable|string'
            ]);

            $demande = Demande::findOrFail($id);
            $ancienStatut = $demande->statut;

            $demande->update([
                'statut' => $request->statut,
                'traite_par' => $user->id,
                'date_traitement' => now(),
                'commentaire_admin' => $request->commentaire_admin
            ]);

            // Notifier si changement de statut
            if ($request->statut !== $ancienStatut) {
                Notification::notifierStatutDemande($demande);
            }

            $demande->load(['user', 'machine', 'composant', 'traitePar']);

            return response()->json([
                'message' => 'Statut de la demande mis à jour avec succès',
                'data' => $demande
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors du changement de statut',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function statistiques()
    {
        try {
            $user = request()->user();
            $query = Demande::query();

            if (!$user->isAdmin()) {
                $query->where('user_id', $user->id);
            }

            $stats = [
                'total' => $query->count(),
                'en_attente' => (clone $query)->where('statut', 'en_attente')->count(),
                'en_cours' => (clone $query)->where('statut', 'en_cours')->count(),
                'acceptees' => (clone $query)->where('statut', 'acceptee')->count(),
                'refusees' => (clone $query)->where('statut', 'refusee')->count(),
                'terminees' => (clone $query)->where('statut', 'terminee')->count(),
                'urgentes' => (clone $query)->whereIn('priorite', ['haute', 'critique'])->count(),
            ];

            // Statistiques par type de demande
            $stats['par_type'] = [
                'maintenance' => (clone $query)->where('type_demande', 'maintenance')->count(),
                'piece' => (clone $query)->where('type_demande', 'piece')->count(),
                'reparation' => (clone $query)->where('type_demande', 'reparation')->count(),
                'inspection' => (clone $query)->where('type_demande', 'inspection')->count(),
            ];

            // Statistiques temporelles (si admin)
            if ($user->isAdmin()) {
                $stats['cette_semaine'] = Demande::whereBetween('created_at', [
                    now()->startOfWeek(),
                    now()->endOfWeek()
                ])->count();

                $stats['ce_mois'] = Demande::whereBetween('created_at', [
                    now()->startOfMonth(),
                    now()->endOfMonth()
                ])->count();
            }

            return response()->json([
                'message' => 'Statistiques des demandes récupérées avec succès',
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors du calcul des statistiques',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function mesDemandesRecentes()
    {
        try {
            $user = request()->user();
            
            $demandes = Demande::where('user_id', $user->id)
                ->with(['machine', 'composant'])
                ->orderBy('created_at', 'desc')
                ->take(10)
                ->get();

            $demandes->transform(function ($demande) {
                $demande->append(['statut_color', 'priorite_color']);
                return $demande;
            });

            return response()->json([
                'message' => 'Demandes récentes récupérées avec succès',
                'data' => $demandes
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des demandes récentes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function demandesEnAttente()
    {
        try {
            $user = request()->user();

            if (!$user->isAdmin()) {
                return response()->json([
                    'message' => 'Action non autorisée'
                ], 403);
            }

            $demandes = Demande::enAttente()
                ->with(['user', 'machine', 'composant'])
                ->orderBy('priorite', 'desc')
                ->orderBy('created_at', 'asc')
                ->get();

            $demandes->transform(function ($demande) {
                $demande->append(['statut_color', 'priorite_color']);
                return $demande;
            });

            return response()->json([
                'message' => 'Demandes en attente récupérées avec succès',
                'data' => $demandes
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des demandes en attente',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function demandesUrgentes()
    {
        try {
            $user = request()->user();

            if (!$user->isAdmin()) {
                return response()->json([
                    'message' => 'Action non autorisée'
                ], 403);
            }

            $demandes = Demande::urgentes()
                ->with(['user', 'machine', 'composant'])
                ->orderBy('priorite', 'desc')
                ->orderBy('created_at', 'asc')
                ->get();

            $demandes->transform(function ($demande) {
                $demande->append(['statut_color', 'priorite_color']);
                return $demande;
            });

            return response()->json([
                'message' => 'Demandes urgentes récupérées avec succès',
                'data' => $demandes
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des demandes urgentes',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}