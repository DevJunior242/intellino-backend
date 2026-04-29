<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use Illuminate\Http\Request;
use App\Models\EquipmentLoan;
use App\Models\EquipmentCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Requests\StoreEquipmntLoanReq;
use App\Http\Requests\StoreEquipmentRequest;

class EquipementController extends Controller
{
    public function index(Request $request)
    {
        $activeId = $request->attributes->get('organisateur_id');
        $equipments = Equipment::where('club_id', $activeId)
            ->with('equipmentCategory')
            ->latest()
            ->paginate(4);
        return response()->json([
            'success' => true,
            'message' => 'Equipements list',
            'equipments' => $equipments
        ], 200);
    }
    //recuperer les materiels pretés
    public function getPret(Request $request)
    {
        $activeId = $request->attributes->get('organisateur_id');
        Log::info('activeId', ['activeId' => $activeId]);
        $equipments = EquipmentLoan::where('club_id', $activeId)
            ->with(['equipment', 'user:id,fullname', 'toClub:id,name'])
            ->latest()
            ->paginate(12);
        return response()->json([
            'success' => true,
            'message' => 'Equipements list',
            'equipments' => $equipments
        ], 200);
    }





    public function getCategories(Request $request)
    {
        $activeId = $request->attributes->get('organisateur_id');
        $equipments = EquipmentCategory::where('club_id', $activeId)
            ->orderBy('name')
            ->get();
        return response()->json([
            'success' => true,
            'message' => 'catégories list',
            'categories' => $equipments
        ]);
    }



    // Créer la catégorie
    public function storeCategory(Request $request)
    {
        $activeId = $request->attributes->get('organisateur_id');


        $request->validate([
            'name' => 'required|unique:equipment_categories,name|string|max:255',
        ], [
            'name.required' => 'Le nom de la catégorie est requis.',
            'name.unique' => 'La catégorie existe déjà.',
        ]);

        $data = [
            'club_id' => $activeId,
            'name' => $request->name
        ];
        $cat = EquipmentCategory::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Catégorie créé avec succès.',
            'category' => $cat,
        ], 201);
    }

    // Créer l'équipement
    public function store(StoreEquipmentRequest $request)
    {
        $data = $request->validated();

        $equipment = Equipment::create([
            ...$data,
            'club_id' => $request->attributes->get('organisateur_id'),
            'available_quantity' => $data['total_quantity'],
            'min_stock_alert' => $data['min_stock_alert'] ?? 2,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Équipement créé avec succès',
            'data' => $equipment,
        ], 201);
    }

    // Prêter le matériel (Le "Out" temporaire)
    public function loanEquipment(StoreEquipmntLoanReq $request)
    {
        $activeId = $request->attributes->get('organisateur_id');
        $equipment = Equipment::findOrFail($request->equipment_id);
        if ($equipment->available_quantity < $request->quantity_loaned) {
            return response()->json(['message' => 'Plus de stock disponible'], 422);
        }

        // 1. On crée le prêt
        $data = $request->validated();

        EquipmentLoan::create([
            ...$data,
            'equipment_id' => $equipment->id,
            'loaned_at' => now(),
            'club_id' => $activeId,
            'user_id' => auth()->id(),
        ]);

        // 2. On diminue la disponibilité
        $equipment->decrement('available_quantity', $request->quantity_loaned);

        return response()->json([
            'data' => $equipment,
            'success' => true,
            'message' => 'Prêt enregistré avec succès'
        ], 201);
    }

    public function returnEquipment(EquipmentLoan $equipmentLoan, Request $request)
    {

        $request->validate([
            'quantity_returned' => 'required|integer|min:0',
            'quantity_lost' => 'required|integer|min:0',
            'quantity_damaged' => 'required|integer|min:0',
        ]);
        try {


            $equipment = $equipmentLoan?->equipment;

            DB::transaction(function () use ($equipmentLoan, $equipment, $request) {
                $returnedQty = (int) $request->quantity_returned;
                $lostQty = (int) $request->quantity_lost;
                $damagedQty = (int) $request->quantity_damaged;




                $total = (int) $equipmentLoan->quantity_loaned;

                $status = $equipmentLoan->calculateStatus($returnedQty, $lostQty, $damagedQty, $total);

                $equipmentLoan->update([
                    'returned_at' => now()->format('Y-m-d H:i:s'),
                    'quantity_returned' => $returnedQty,
                    'quantity_lost' => $lostQty,
                    'quantity_damaged' => $damagedQty,
                    'status' => $status,
                ]);

                //verfifier le total remis avant imcrement
                $totalLoanReturned = (int) $equipmentLoan->quantity_returned + (int) $equipmentLoan->quantity_lost + (int) $equipmentLoan->quantity_damaged;

                if ($totalLoanReturned > $total) {
                    throw new \Exception('Quantités invalides');
                }
                $equipment->increment('available_quantity', $returnedQty);

                return $equipment;
            });

            return response()->json([
                'success' => true,
                'message' => 'Prêt retourné avec succès',
                'data' => $equipmentLoan
            ], 201);
        } catch (\Throwable $e) {
            # code...
            throw $e;

            return response()->json([
                'success' => false,
                'message' => 'une erreur est survenue',
                'data' => $equipmentLoan
            ], 422);
        }
    }
}
