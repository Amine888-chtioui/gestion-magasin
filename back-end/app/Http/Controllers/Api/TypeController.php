<?php
// app/Http/Controllers/Api/TypeController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Type;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TypeController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Type::query();

            // Filtres
            if ($request->has('actif')) {
                $query->where('actif', $request->boolean('actif'));
            }

            if ($request->has('search')) {
                $query->where('nom', 'like', '%' . $request->search . '%');
            }

            // Tri
            $sortBy = $request->get('sort_by', 'nom');
            $sortOrder = $request->get('sort_order', 'asc');
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = $request->get('per_page', 15);
            $types = $query->withCount('composants')->paginate($perPage);

            return response()->json([
                'message' => 'Types récupérés avec succès',
                'data' => $types
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des types',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'nom' => 'required|string|max:100|unique:types,nom',
                'description' => 'nullable|string',
                'couleur' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
                'actif' => 'boolean'
            ]);

            $type = Type::create($request->all());

            return response()->json([
                'message' => 'Type créé avec succès',
                'data' => $type
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la création du type',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $type = Type::with(['composants.machine'])
                ->withCount('composants')
                ->findOrFail($id);

            return response()->json([
                'message' => 'Type récupéré avec succès',
                'data' => $type
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Type non trouvé',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $type = Type::findOrFail($id);

            $request->validate([
                'nom' => 'sometimes|string|max:100|unique:types,nom,' . $id,
                'description' => 'nullable|string',
                'couleur' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
                'actif' => 'boolean'
            ]);

            $type->update($request->all());

            return response()->json([
                'message' => 'Type mis à jour avec succès',
                'data' => $type
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la mise à jour du type',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $type = Type::findOrFail($id);

            // Vérifier s'il y a des composants associés
            if ($type->composants()->count() > 0) {
                return response()->json([
                    'message' => 'Impossible de supprimer ce type car il est utilisé par des composants'
                ], 409);
            }

            $type->delete();

            return response()->json([
                'message' => 'Type supprimé avec succès'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la suppression du type',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getActifs()
    {
        try {
            $types = Type::actifs()
                ->orderBy('nom')
                ->get(['id', 'nom', 'couleur']);

            return response()->json([
                'message' => 'Types actifs récupérés avec succès',
                'data' => $types
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des types actifs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function toggleActif($id)
    {
        try {
            $type = Type::findOrFail($id);
            $type->actif = !$type->actif;
            $type->save();

            return response()->json([
                'message' => 'Statut du type mis à jour avec succès',
                'data' => $type
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la mise à jour du statut',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function statistiques()
    {
        try {
            $stats = [
                'total' => Type::count(),
                'actifs' => Type::where('actif', true)->count(),
                'inactifs' => Type::where('actif', false)->count(),
                'avec_composants' => Type::has('composants')->count(),
                'sans_composants' => Type::doesntHave('composants')->count(),
            ];

            return response()->json([
                'message' => 'Statistiques des types récupérées avec succès',
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors du calcul des statistiques',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}