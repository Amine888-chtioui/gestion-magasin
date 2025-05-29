<?php
// app/Http/Controllers/Api/NotificationController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $query = Notification::where('user_id', $user->id);

            // Filtres
            if ($request->has('lu')) {
                if ($request->boolean('lu')) {
                    $query->lues();
                } else {
                    $query->nonLues();
                }
            }

            if ($request->has('type')) {
                $query->parType($request->type);
            }

            if ($request->has('recentes')) {
                $jours = $request->get('jours', 7);
                $query->recentes($jours);
            }

            // Tri
            $query->orderBy('created_at', 'desc');

            // Pagination
            $perPage = $request->get('per_page', 20);
            $notifications = $query->paginate($perPage);

            // Ajouter des données calculées
            $notifications->getCollection()->transform(function ($notification) {
                $notification->append(['type_color', 'type_icon', 'temps_ecoule']);
                return $notification;
            });

            return response()->json([
                'message' => 'Notifications récupérées avec succès',
                'data' => $notifications
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $user = request()->user();
            $notification = Notification::where('user_id', $user->id)
                ->findOrFail($id);

            // Marquer comme lue automatiquement
            if (!$notification->lu) {
                $notification->marquerCommeLue();
            }

            $notification->append(['type_color', 'type_icon', 'temps_ecoule']);

            return response()->json([
                'message' => 'Notification récupérée avec succès',
                'data' => $notification
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Notification non trouvée',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function marquerCommeLue($id)
    {
        try {
            $user = request()->user();
            $notification = Notification::where('user_id', $user->id)
                ->findOrFail($id);

            $notification->marquerCommeLue();

            return response()->json([
                'message' => 'Notification marquée comme lue',
                'data' => $notification
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors du marquage de la notification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function marquerCommeNonLue($id)
    {
        try {
            $user = request()->user();
            $notification = Notification::where('user_id', $user->id)
                ->findOrFail($id);

            $notification->marquerCommeNonLue();

            return response()->json([
                'message' => 'Notification marquée comme non lue',
                'data' => $notification
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors du marquage de la notification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function marquerToutesCommeLues()
    {
        try {
            $user = request()->user();
            
            Notification::where('user_id', $user->id)
                ->where('lu', false)
                ->update([
                    'lu' => true,
                    'lu_le' => now()
                ]);

            return response()->json([
                'message' => 'Toutes les notifications ont été marquées comme lues'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors du marquage des notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $user = request()->user();
            $notification = Notification::where('user_id', $user->id)
                ->findOrFail($id);

            $notification->delete();

            return response()->json([
                'message' => 'Notification supprimée avec succès'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la suppression de la notification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function supprimerLues()
    {
        try {
            $user = request()->user();
            
            $count = Notification::where('user_id', $user->id)
                ->where('lu', true)
                ->delete();

            return response()->json([
                'message' => "($count) notifications lues supprimées avec succès"
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la suppression des notifications lues',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getNonLues()
    {
        try {
            $user = request()->user();
            
            $notifications = Notification::where('user_id', $user->id)
                ->nonLues()
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            $notifications->transform(function ($notification) {
                $notification->append(['type_color', 'type_icon', 'temps_ecoule']);
                return $notification;
            });

            return response()->json([
                'message' => 'Notifications non lues récupérées avec succès',
                'data' => $notifications,
                'count' => $notifications->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des notifications non lues',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getRecentes()
    {
        try {
            $user = request()->user();
            
            $notifications = Notification::where('user_id', $user->id)
                ->recentes(7)
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get();

            $notifications->transform(function ($notification) {
                $notification->append(['type_color', 'type_icon', 'temps_ecoule']);
                return $notification;
            });

            return response()->json([
                'message' => 'Notifications récentes récupérées avec succès',
                'data' => $notifications
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des notifications récentes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getCount()
    {
        try {
            $user = request()->user();
            
            $counts = [
                'total' => Notification::where('user_id', $user->id)->count(),
                'non_lues' => Notification::where('user_id', $user->id)->nonLues()->count(),
                'lues' => Notification::where('user_id', $user->id)->lues()->count(),
                'recentes' => Notification::where('user_id', $user->id)->recentes(7)->count(),
            ];

            return response()->json([
                'message' => 'Compteurs de notifications récupérés avec succès',
                'data' => $counts
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors du calcul des compteurs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function creer(Request $request)
    {
        try {
            $user = $request->user();

            // Seuls les admins peuvent créer des notifications manuelles
            if (!$user->isAdmin()) {
                return response()->json([
                    'message' => 'Action non autorisée'
                ], 403);
            }

            $request->validate([
                'user_id' => 'required|exists:users,id',
                'titre' => 'required|string|max:150',
                'message' => 'required|string',
                'type' => 'sometimes|in:info,success,warning,error',
                'data' => 'nullable|array'
            ]);

            $notification = Notification::creerNotification(
                $request->user_id,
                $request->titre,
                $request->message,
                $request->get('type', 'info'),
                $request->data
            );

            return response()->json([
                'message' => 'Notification créée avec succès',
                'data' => $notification
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la création de la notification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function diffuser(Request $request)
    {
        try {
            $user = $request->user();

            // Seuls les admins peuvent diffuser des notifications
            if (!$user->isAdmin()) {
                return response()->json([
                    'message' => 'Action non autorisée'
                ], 403);
            }

            $request->validate([
                'titre' => 'required|string|max:150',
                'message' => 'required|string',
                'type' => 'sometimes|in:info,success,warning,error',
                'destinataires' => 'required|in:tous,admins,users',
                'data' => 'nullable|array'
            ]);

            $query = \App\Models\User::query();
            
            if ($request->destinataires === 'admins') {
                $query->where('role', 'admin');
            } elseif ($request->destinataires === 'users') {
                $query->where('role', 'user');
            }

            $utilisateurs = $query->where('actif', true)->get();
            $count = 0;

            foreach ($utilisateurs as $utilisateur) {
                Notification::creerNotification(
                    $utilisateur->id,
                    $request->titre,
                    $request->message,
                    $request->get('type', 'info'),
                    $request->data
                );
                $count++;
            }

            return response()->json([
                'message' => "Notification diffusée à {$count} utilisateur(s)"
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la diffusion de la notification',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}