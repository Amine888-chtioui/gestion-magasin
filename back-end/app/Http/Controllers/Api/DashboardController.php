<?php
// app/Http/Controllers/Api/DashboardController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Machine;
use App\Models\Composant;
use App\Models\Demande;
use App\Models\Type;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = $request->user();

            if ($user->isAdmin()) {
                return $this->dashboardAdmin();
            } else {
                return $this->dashboardUser($user);
            }

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des données du dashboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function dashboardAdmin()
    {
        // Statistiques générales
        $statistiques = [
            'machines' => [
                'total' => Machine::count(),
                'actives' => Machine::where('statut', 'actif')->count(),
                'en_maintenance' => Machine::where('statut', 'maintenance')->count(),
                'inactives' => Machine::where('statut', 'inactif')->count(),
                'necessitent_maintenance' => Machine::necessiteMaintenace()->count(),
            ],
            'composants' => [
                'total' => Composant::count(),
                'bon' => Composant::where('statut', 'bon')->count(),
                'usure' => Composant::where('statut', 'usure')->count(),
                'defaillant' => Composant::where('statut', 'defaillant')->count(),
                'a_inspecter' => Composant::aInspecter()->count(),
                'valeur_totale' => Composant::sum(DB::raw('prix_unitaire * quantite')),
            ],
            'demandes' => [
                'total' => Demande::count(),
                'en_attente' => Demande::where('statut', 'en_attente')->count(),
                'en_cours' => Demande::where('statut', 'en_cours')->count(),
                'urgentes' => Demande::whereIn('priorite', ['haute', 'critique'])->count(),
                'cette_semaine' => Demande::whereBetween('created_at', [
                    Carbon::now()->startOfWeek(),
                    Carbon::now()->endOfWeek()
                ])->count(),
                'ce_mois' => Demande::whereBetween('created_at', [
                    Carbon::now()->startOfMonth(),
                    Carbon::now()->endOfMonth()
                ])->count(),
            ],
            'utilisateurs' => [
                'total' => User::count(),
                'actifs' => User::where('actif', true)->count(),
                'admins' => User::where('role', 'admin')->count(),
                'users' => User::where('role', 'user')->count(),
            ]
        ];

        // Alertes et notifications importantes
        $alertes = [
            'composants_defaillants' => Composant::defaillants()
                ->with(['machine', 'type'])
                ->orderBy('updated_at', 'desc')
                ->take(5)
                ->get(),
            'composants_a_inspecter' => Composant::aInspecter()
                ->with(['machine', 'type'])
                ->orderBy('prochaine_inspection')
                ->take(5)
                ->get(),
            'machines_maintenance' => Machine::necessiteMaintenace()
                ->orderBy('derniere_maintenance')
                ->take(5)
                ->get(),
            'demandes_urgentes' => Demande::urgentes()
                ->where('statut', 'en_attente')
                ->with(['user', 'machine'])
                ->orderBy('created_at', 'asc')
                ->take(5)
                ->get(),
        ];

        // Graphiques et tendances
        $graphiques = [
            'demandes_par_mois' => $this->getDemandesParMois(),
            'demandes_par_type' => $this->getDemandesParType(),
            'demandes_par_priorite' => $this->getDemandesParPriorite(),
            'composants_par_statut' => $this->getComposantsParStatut(),
            'machines_par_statut' => $this->getMachinesParStatut(),
            'evolution_maintenance' => $this->getEvolutionMaintenance(),
        ];

        // Activités récentes
        $activites_recentes = [
            'demandes_recentes' => Demande::with(['user', 'machine'])
                ->orderBy('created_at', 'desc')
                ->take(10)
                ->get(),
            'composants_modifies' => Composant::with(['machine', 'type'])
                ->orderBy('updated_at', 'desc')
                ->take(10)
                ->get(),
            'notifications_non_lues' => Notification::nonLues()
                ->orderBy('created_at', 'desc')
                ->take(10)
                ->get(),
        ];

        return response()->json([
            'message' => 'Dashboard admin récupéré avec succès',
            'data' => [
                'statistiques' => $statistiques,
                'alertes' => $alertes,
                'graphiques' => $graphiques,
                'activites_recentes' => $activites_recentes,
                'resume' => $this->genererResumeAdmin($statistiques, $alertes),
            ]
        ]);
    }

    private function dashboardUser($user)
    {
        // Statistiques personnelles
        $mes_statistiques = [
            'mes_demandes' => [
                'total' => $user->demandes()->count(),
                'en_attente' => $user->demandes()->where('statut', 'en_attente')->count(),
                'acceptees' => $user->demandes()->where('statut', 'acceptee')->count(),
                'refusees' => $user->demandes()->where('statut', 'refusee')->count(),
                'terminees' => $user->demandes()->where('statut', 'terminee')->count(),
                'ce_mois' => $user->demandes()->whereBetween('created_at', [
                    Carbon::now()->startOfMonth(),
                    Carbon::now()->endOfMonth()
                ])->count(),
            ]
        ];

        // Mes demandes récentes
        $mes_demandes_recentes = $user->demandes()
            ->with(['machine', 'composant'])
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get()
            ->map(function ($demande) {
                $demande->append(['statut_color', 'priorite_color']);
                return $demande;
            });

        // Statistiques générales (limitées pour les utilisateurs)
        $statistiques_generales = [
            'machines' => [
                'total' => Machine::count(),
                'actives' => Machine::where('statut', 'actif')->count(),
            ],
            'composants' => [
                'total' => Composant::count(),
                'bon' => Composant::where('statut', 'bon')->count(),
            ],
        ];

        // Alertes importantes (visibles par tous)
        $alertes_importantes = [
            'machines_en_maintenance' => Machine::where('statut', 'maintenance')
                ->orderBy('updated_at', 'desc')
                ->take(5)
                ->get(['id', 'nom', 'localisation', 'statut']),
            'composants_defaillants_count' => Composant::where('statut', 'defaillant')->count(),
        ];

        // Mes graphiques personnels
        $mes_graphiques = [
            'mes_demandes_par_mois' => $this->getMesDemandesParMois($user),
            'mes_demandes_par_type' => $this->getMesDemandesParType($user),
            'mes_demandes_par_statut' => $this->getMesDemandesParStatut($user),
        ];

        return response()->json([
            'message' => 'Dashboard utilisateur récupéré avec succès',
            'data' => [
                'mes_statistiques' => $mes_statistiques,
                'mes_demandes_recentes' => $mes_demandes_recentes,
                'statistiques_generales' => $statistiques_generales,
                'alertes_importantes' => $alertes_importantes,
                'mes_graphiques' => $mes_graphiques,
                'resume' => $this->genererResumeUser($user, $mes_statistiques),
            ]
        ]);
    }

    // Méthodes pour les graphiques Admin
    private function getDemandesParMois()
    {
        return Demande::select(
            DB::raw('YEAR(created_at) as annee'),
            DB::raw('MONTH(created_at) as mois'),
            DB::raw('COUNT(*) as total')
        )
        ->where('created_at', '>=', Carbon::now()->subMonths(12))
        ->groupBy('annee', 'mois')
        ->orderBy('annee', 'asc')
        ->orderBy('mois', 'asc')
        ->get()
        ->map(function ($item) {
            $item->periode = Carbon::create($item->annee, $item->mois, 1)->format('M Y');
            return $item;
        });
    }

    private function getDemandesParType()
    {
        return Demande::select('type_demande', DB::raw('COUNT(*) as total'))
            ->groupBy('type_demande')
            ->get();
    }

    private function getDemandesParPriorite()
    {
        return Demande::select('priorite', DB::raw('COUNT(*) as total'))
            ->groupBy('priorite')
            ->get();
    }

    private function getComposantsParStatut()
    {
        return Composant::select('statut', DB::raw('COUNT(*) as total'))
            ->groupBy('statut')
            ->get();
    }

    private function getMachinesParStatut()
    {
        return Machine::select('statut', DB::raw('COUNT(*) as total'))
            ->groupBy('statut')
            ->get();
    }

    private function getEvolutionMaintenance()
    {
        return Machine::select(
            DB::raw('DATE(derniere_maintenance) as date'),
            DB::raw('COUNT(*) as total')
        )
        ->whereNotNull('derniere_maintenance')
        ->where('derniere_maintenance', '>=', Carbon::now()->subMonths(6))
        ->groupBy('date')
        ->orderBy('date', 'asc')
        ->get();
    }

    // Méthodes pour les graphiques User
    private function getMesDemandesParMois($user)
    {
        return $user->demandes()
            ->select(
                DB::raw('YEAR(created_at) as annee'),
                DB::raw('MONTH(created_at) as mois'),
                DB::raw('COUNT(*) as total')
            )
            ->where('created_at', '>=', Carbon::now()->subMonths(12))
            ->groupBy('annee', 'mois')
            ->orderBy('annee', 'asc')
            ->orderBy('mois', 'asc')
            ->get()
            ->map(function ($item) {
                $item->periode = Carbon::create($item->annee, $item->mois, 1)->format('M Y');
                return $item;
            });
    }

    private function getMesDemandesParType($user)
    {
        return $user->demandes()
            ->select('type_demande', DB::raw('COUNT(*) as total'))
            ->groupBy('type_demande')
            ->get();
    }

    private function getMesDemandesParStatut($user)
    {
        return $user->demandes()
            ->select('statut', DB::raw('COUNT(*) as total'))
            ->groupBy('statut')
            ->get();
    }

    // Méthodes pour les résumés
    private function genererResumeAdmin($statistiques, $alertes)
    {
        $resume = [];

        // Messages d'état général
        if ($statistiques['demandes']['en_attente'] > 0) {
            $resume[] = [
                'type' => 'warning',
                'message' => "{$statistiques['demandes']['en_attente']} demande(s) en attente de traitement",
                'action' => 'Consulter les demandes en attente'
            ];
        }

        if ($alertes['composants_defaillants']->count() > 0) {
            $resume[] = [
                'type' => 'error',
                'message' => "{$alertes['composants_defaillants']->count()} composant(s) défaillant(s) nécessitent une attention immédiate",
                'action' => 'Voir les composants défaillants'
            ];
        }

        if ($alertes['machines_maintenance']->count() > 0) {
            $resume[] = [
                'type' => 'warning',
                'message' => "{$alertes['machines_maintenance']->count()} machine(s) nécessitent une maintenance",
                'action' => 'Planifier les maintenances'
            ];
        }

        if ($alertes['composants_a_inspecter']->count() > 0) {
            $resume[] = [
                'type' => 'info',
                'message' => "{$alertes['composants_a_inspecter']->count()} composant(s) à inspecter bientôt",
                'action' => 'Programmer les inspections'
            ];
        }

        if (empty($resume)) {
            $resume[] = [
                'type' => 'success',
                'message' => 'Tous les systèmes fonctionnent normalement',
                'action' => null
            ];
        }

        return $resume;
    }

    private function genererResumeUser($user, $mes_statistiques)
    {
        $resume = [];

        if ($mes_statistiques['mes_demandes']['en_attente'] > 0) {
            $resume[] = [
                'type' => 'info',
                'message' => "Vous avez {$mes_statistiques['mes_demandes']['en_attente']} demande(s) en attente",
                'action' => 'Suivre mes demandes'
            ];
        }

        if ($mes_statistiques['mes_demandes']['total'] === 0) {
            $resume[] = [
                'type' => 'info',
                'message' => 'Vous n\'avez pas encore soumis de demandes',
                'action' => 'Créer une nouvelle demande'
            ];
        }

        return $resume;
    }

    // Endpoints spécifiques supplémentaires
    public function getStatistiquesGenerales()
    {
        try {
            $user = request()->user();

            if ($user->isAdmin()) {
                $stats = [
                    'machines' => [
                        'total' => Machine::count(),
                        'actives' => Machine::where('statut', 'actif')->count(),
                        'en_maintenance' => Machine::where('statut', 'maintenance')->count(),
                        'inactives' => Machine::where('statut', 'inactif')->count(),
                    ],
                    'composants' => [
                        'total' => Composant::count(),
                        'bon' => Composant::where('statut', 'bon')->count(),
                        'usure' => Composant::where('statut', 'usure')->count(),
                        'defaillant' => Composant::where('statut', 'defaillant')->count(),
                    ],
                    'demandes' => [
                        'total' => Demande::count(),
                        'en_attente' => Demande::where('statut', 'en_attente')->count(),
                        'urgentes' => Demande::whereIn('priorite', ['haute', 'critique'])->count(),
                    ],
                ];
            } else {
                $stats = [
                    'mes_demandes' => [
                        'total' => $user->demandes()->count(),
                        'en_attente' => $user->demandes()->where('statut', 'en_attente')->count(),
                    ],
                    'machines_actives' => Machine::where('statut', 'actif')->count(),
                ];
            }

            return response()->json([
                'message' => 'Statistiques générales récupérées avec succès',
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des statistiques',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getAlertes()
    {
        try {
            $user = request()->user();

            if (!$user->isAdmin()) {
                return response()->json([
                    'message' => 'Action non autorisée'
                ], 403);
            }

            $alertes = [
                'composants_defaillants' => Composant::where('statut', 'defaillant')->count(),
                'machines_maintenance' => Machine::where('statut', 'maintenance')->count(),
                'demandes_urgentes' => Demande::whereIn('priorite', ['haute', 'critique'])
                    ->where('statut', 'en_attente')->count(),
                'composants_a_inspecter' => Composant::where('prochaine_inspection', '<=', now()->addDays(7))->count(),
            ];

            return response()->json([
                'message' => 'Alertes récupérées avec succès',
                'data' => $alertes
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des alertes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getActivitesRecentes()
    {
        try {
            $user = request()->user();

            if ($user->isAdmin()) {
                $activites = [
                    'demandes_recentes' => Demande::with(['user', 'machine'])
                        ->orderBy('created_at', 'desc')
                        ->take(10)
                        ->get(),
                    'machines_modifiees' => Machine::orderBy('updated_at', 'desc')
                        ->take(5)
                        ->get(['id', 'nom', 'statut', 'updated_at']),
                    'composants_modifies' => Composant::with(['machine'])
                        ->orderBy('updated_at', 'desc')
                        ->take(5)
                        ->get(['id', 'nom', 'statut', 'machine_id', 'updated_at']),
                ];
            } else {
                $activites = [
                    'mes_demandes_recentes' => $user->demandes()
                        ->with(['machine'])
                        ->orderBy('created_at', 'desc')
                        ->take(5)
                        ->get(),
                ];
            }

            return response()->json([
                'message' => 'Activités récentes récupérées avec succès',
                'data' => $activites
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des activités',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getGraphiqueEvolution()
    {
        try {
            $user = request()->user();

            if (!$user->isAdmin()) {
                return response()->json([
                    'message' => 'Action non autorisée'
                ], 403);
            }

            $evolution = [
                'demandes_par_mois' => $this->getDemandesParMois(),
                'machines_par_statut' => $this->getMachinesParStatut(),
                'composants_par_statut' => $this->getComposantsParStatut(),
                'evolution_maintenance' => $this->getEvolutionMaintenance(),
            ];

            return response()->json([
                'message' => 'Graphiques d\'évolution récupérés avec succès',
                'data' => $evolution
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des graphiques',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getResume()
    {
        try {
            $user = request()->user();
            $resume = [];

            if ($user->isAdmin()) {
                $demandesEnAttente = Demande::where('statut', 'en_attente')->count();
                $composantsDefaillants = Composant::where('statut', 'defaillant')->count();
                $machinesMaintenance = Machine::where('statut', 'maintenance')->count();
                
                if ($demandesEnAttente > 0) {
                    $resume[] = [
                        'type' => 'warning',
                        'message' => "$demandesEnAttente demande(s) en attente de traitement",
                        'action' => 'Consulter les demandes'
                    ];
                }
                
                if ($composantsDefaillants > 0) {
                    $resume[] = [
                        'type' => 'error',
                        'message' => "$composantsDefaillants composant(s) défaillant(s)",
                        'action' => 'Vérifier les composants'
                    ];
                }

                if ($machinesMaintenance > 0) {
                    $resume[] = [
                        'type' => 'info',
                        'message' => "$machinesMaintenance machine(s) en maintenance",
                        'action' => 'Suivre les maintenances'
                    ];
                }
            } else {
                $mesDemandesEnAttente = $user->demandes()->where('statut', 'en_attente')->count();
                $mesDemandesTotal = $user->demandes()->count();
                
                if ($mesDemandesEnAttente > 0) {
                    $resume[] = [
                        'type' => 'info',
                        'message' => "Vous avez $mesDemandesEnAttente demande(s) en attente",
                        'action' => 'Suivre vos demandes'
                    ];
                }

                if ($mesDemandesTotal === 0) {
                    $resume[] = [
                        'type' => 'info',
                        'message' => "Vous n'avez pas encore soumis de demandes",
                        'action' => 'Créer une demande'
                    ];
                }
            }

            if (empty($resume)) {
                $resume[] = [
                    'type' => 'success',
                    'message' => 'Tout fonctionne normalement !',
                    'action' => null
                ];
            }

            return response()->json([
                'message' => 'Résumé récupéré avec succès',
                'data' => $resume
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération du résumé',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Endpoints rapides
    public function statistiquesRapides()
    {
        try {
            $user = request()->user();

            if ($user->isAdmin()) {
                $stats = [
                    'machines_actives' => Machine::where('statut', 'actif')->count(),
                    'demandes_en_attente' => Demande::where('statut', 'en_attente')->count(),
                    'composants_defaillants' => Composant::where('statut', 'defaillant')->count(),
                    'notifications_non_lues' => Notification::nonLues()->count(),
                ];
            } else {
                $stats = [
                    'mes_demandes_total' => $user->demandes()->count(),
                    'mes_demandes_en_attente' => $user->demandes()->where('statut', 'en_attente')->count(),
                    'mes_notifications_non_lues' => $user->notifications()->nonLues()->count(),
                ];
            }

            return response()->json([
                'message' => 'Statistiques rapides récupérées avec succès',
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des statistiques rapides',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function alertesImportantes()
    {
        try {
            $user = request()->user();

            if (!$user->isAdmin()) {
                return response()->json([
                    'message' => 'Action non autorisée'
                ], 403);
            }

            $alertes = [
                'critique' => [
                    'composants_defaillants' => Composant::where('statut', 'defaillant')->count(),
                    'demandes_critiques' => Demande::where('priorite', 'critique')
                        ->where('statut', 'en_attente')->count(),
                ],
                'attention' => [
                    'machines_maintenance' => Machine::necessiteMaintenace()->count(),
                    'composants_usure' => Composant::where('statut', 'usure')->count(),
                    'inspections_retard' => Composant::where('prochaine_inspection', '<', Carbon::now())->count(),
                ],
                'info' => [
                    'inspections_semaine' => Composant::whereBetween('prochaine_inspection', [
                        Carbon::now(),
                        Carbon::now()->addWeek()
                    ])->count(),
                ]
            ];

            return response()->json([
                'message' => 'Alertes importantes récupérées avec succès',
                'data' => $alertes
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des alertes',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}