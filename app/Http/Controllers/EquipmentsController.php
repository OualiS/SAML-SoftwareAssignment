<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EquipmentsController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $search = isset($validated['search']) ? trim($validated['search']) : null;
        if ($search === '') {
            $search = null;
        }

        $equipments = DB::table('equipments')
            ->select(['Equipment', 'Material', 'Description', 'Room'])
            ->when($search !== null, function (Builder $query) use ($search): void {
                $like = '%' . $search . '%';

                $query->where(function (Builder $query) use ($like): void {
                    $query->where('Equipment', 'like', $like)
                        ->orWhere('Material', 'like', $like)
                        ->orWhere('Description', 'like', $like)
                        ->orWhere('Room', 'like', $like);
                });
            })
            ->orderBy('Equipment')
            ->paginate(25)
            ->withQueryString();

        return view('equipments.index', [
            'equipments' => $equipments,
            'search' => $search,
        ]);
    }
}
