<?php
// app/Http/Controllers/Api/ComposantController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Composant;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class ComposantController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Composant::with(['machine', 'type']);

            // Filtres
            if ($request->has('machine_id')) {
                $query->where('machine_id', $request->machine_id);
            }

            if ($request->has('type_id')) {
                $query->where('type_id', $request->type_id);
            }

            if ($request->has('statut')) {
                $query->where('statut', $request->statut);
            }

            if ($request->has('fournisseur')) {
                $query->where('fournisseur', 'like', '%' . $request->fournisseur . '%');
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('nom', 'like', "%{$search}%")
                      ->orWhere('reference', 'like', "%{$search}%")
                      ->orWhere('fournisseur', 'like', "%{$search}%");
                });
            }

            // Filtres spéciaux
            if ($request->has('defaillants') && $request->boolean('defaillants')) {
                $query->defaillants();
            }

            if ($request->has('a_inspecter') && $request->boolean('a_inspecter')) {
                $query->aInspecter();
            }

            if ($request->has('usures') && $request->boolean('usures')) {
                $query->usures();
            }

            // Tri
            $sortBy = $request->get('sort_by', 'nom');
            $sortOrder = $request->get('sort_order', 'asc');
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = $request->get('per_page', 15);
            $composants = $query->paginate($perPage);

            // Ajouter des données calculées
            $composants->getCollection()->transform(function ($composant) {
                $composant->append(['prix_total', 'age', 'statut_inspection', 'pourcentage_vie']);
                return $composant;
            });

            return response()->json([
                'message' => 'Composants récupérés avec succès',
                'data' => $composants
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des composants',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'nom' => 'required|string|max:100',
                'reference' => 'required|string|max:50|unique:composants,reference',
                'machine_id' => 'required|exists:machines,id',
                'type_id' => 'required|exists:types,id',
                'description' => 'nullable|string',
                'statut' => 'sometimes|in:bon,usure,defaillant,remplace',
                'quantite' => 'required|integer|min:1',
                'prix_unitaire' => 'nullable|numeric|min:0',
                'fournisseur' => 'nullable|string|max:100',
                'date_installation' => 'nullable|date',
                'derniere_inspection' => 'nullable|date',
                'prochaine_inspection' => 'nullable|date|after:derniere_inspection',
                'duree_vie_estimee' => 'nullable|integer|min:1',
                'notes' => 'nullable|string',
                'caracteristiques' => 'nullable|array'
            ]);

            $composant = Composant::create($request->all());
            $composant->load(['machine', 'type']);

            return response()->json([
                'message' => 'Composant créé avec succès',
                'data' => $composant
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la création du composant',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $composant = Composant::with([
                'machine',
                'type',
                'demandes' => function($query) {
                    $query->latest()->take(10);
                },
                'demandes.user'
            ])->findOrFail($id);

            $composant->append(['prix_total', 'age', 'statut_inspection', 'pourcentage_vie']);

            return response()->json([
                'message' => 'Composant récupéré avec succès',
                'data' => $composant
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Composant non trouvé',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $composant = Composant::findOrFail($id);

            $request->validate([
                'nom' => 'sometimes|string|max:100',
                'reference' => 'sometimes|string|max:50|unique:composants,reference,' . $id,
                'machine_id' => 'sometimes|exists:machines,id',
                'type_id' => 'sometimes|exists:types,id',
                'description' => 'nullable|string',
                'statut' => 'sometimes|in:bon,usure,defaillant,remplace',
                'quantite' => 'sometimes|integer|min:1',
                'prix_unitaire' => 'nullable|numeric|min:0',
                'fournisseur' => 'nullable|string|max:100',
                'date_installation' => 'nullable|date',
                'derniere_inspection' => 'nullable|date',
                'prochaine_inspection' => 'nullable|date',
                'duree_vie_estimee' => 'nullable|integer|min:1',
                'notes' => 'nullable|string',
                'caracteristiques' => 'nullable|array'
            ]);

            $ancienStatut = $composant->statut;
            $composant->update($request->all());

            // Si le composant devient défaillant, créer une notification
            if ($request->has('statut') && $request->statut === 'defaillant' && $ancienStatut !== 'defaillant') {
                Notification::notifierComposantDefaillant($composant);
            }

            $composant->load(['machine', 'type']);

            return response()->json([
                'message' => 'Composant mis à jour avec succès',
                'data' => $composant
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la mise à jour du composant',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $composant = Composant::findOrFail($id);

            // Vérifier s'il y a des demandes associées
            if ($composant->demandes()->count() > 0) {
                return response()->json([
                    'message' => 'Impossible de supprimer ce composant car il a des demandes associées'
                ], 409);
            }

            $composant->delete();

            return response()->json([
                'message' => 'Composant supprimé avec succès'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la suppression du composant',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateStatut(Request $request, $id)
    {
        try {
            $composant = Composant::findOrFail($id);

            $request->validate([
                'statut' => 'required|in:bon,usure,defaillant,remplace',
                'notes' => 'nullable|string'
            ]);

            $ancienStatut = $composant->statut;
            
            $composant->update([
                'statut' => $request->statut,
                'notes' => $request->notes ?? $composant->notes
            ]);

            // Notification si défaillant
            if ($request->statut === 'defaillant' && $ancienStatut !== 'defaillant') {
                Notification::notifierComposantDefaillant($composant);
            }

            return response()->json([
                'message' => 'Statut du composant mis à jour avec succès',
                'data' => $composant
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la mise à jour du statut',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateInspection(Request $request, $id)
    {
        try {
            $composant = Composant::findOrFail($id);

            $request->validate([
                'derniere_inspection' => 'required|date',
                'prochaine_inspection' => 'nullable|date|after:derniere_inspection',
                'statut' => 'sometimes|in:bon,usure,defaillant,remplace',
                'notes' => 'nullable|string'
            ]);

            $data = $request->only(['derniere_inspection', 'prochaine_inspection', 'notes']);
            
            if ($request->has('statut')) {
                $data['statut'] = $request->statut;
            }

            $composant->update($data);

            return response()->json([
                'message' => 'Inspection du composant mise à jour avec succès',
                'data' => $composant
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la mise à jour de l\'inspection',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function statistiques()
    {
        try {
            $stats = [
                'total' => Composant::count(),
                'bon' => Composant::where('statut', 'bon')->count(),
                'usure' => Composant::where('statut', 'usure')->count(),
                'defaillant' => Composant::where('statut', 'defaillant')->count(),
                'remplace' => Composant::where('statut', 'remplace')->count(),
                'a_inspecter' => Composant::aInspecter()->count(),
                'valeur_totale' => Composant::sum(\DB::raw('prix_unitaire * quantite')),
            ];

            return response()->json([
                'message' => 'Statistiques des composants récupérées avec succès',
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors du calcul des statistiques',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getDefaillants()
    {
        try {
            $composants = Composant::defaillants()
                ->with(['machine', 'type'])
                ->orderBy('updated_at', 'desc')
                ->get();

            return response()->json([
                'message' => 'Composants défaillants récupérés avec succès',
                'data' => $composants
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des composants défaillants',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getAInspecter()
    {
        try {
            $composants = Composant::aInspecter()
                ->with(['machine', 'type'])
                ->orderBy('prochaine_inspection')
                ->get();

            $composants->transform(function ($composant) {
                $composant->append(['statut_inspection']);
                return $composant;
            });

            return response()->json([
                'message' => 'Composants à inspecter récupérés avec succès',
                'data' => $composants
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des composants à inspecter',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}