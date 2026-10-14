<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InputUsage\StoreInputUsageRequest;
use App\Http\Requests\InputUsage\UpdateInputUsageRequest;
use App\Http\Resources\InputUsageResource;
use App\Models\Farmer;
use App\Models\InputUsage;
use App\Models\Plot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class InputUsageController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', InputUsage::class);

        $query = InputUsage::query()
        ->with('input')
        ->with('plot')
        ->with('campaign');
        $user = $request->user();

        $query = match ($user->role) {
            'cooperative_manager' => $query->whereHas('plot', fn ($q) => $q->where('cooperative_id', $user->managedCooperative?->id ?? 0)),
            'farmer' => $query->whereHas('plot', fn ($q) => $q->where('farmer_id', $user->farmer?->id ?? 0)),
            default => $query, // admin, analyst
        };

            if ($cooperativeId = $request->query('cooperative_id')) {
            $query->whereHas('plot', function ($plotQuery) use ($cooperativeId) {
                $plotQuery->where('cooperative_id', $cooperativeId);
            });
        }

        if ($plotId = $request->query('plot_id')) {
            $query->where('plot_id', $plotId);
        }

        if ($campaignId = $request->query('campaign_id')) {
            $query->where('campaign_id', $campaignId);
        }

        return InputUsageResource::collection($query->paginate($this->perPage($request)));
    }

    public function store(StoreInputUsageRequest $request): InputUsageResource
    {
        $user = $request->user();
        $data = $request->validated();

        $plot = Plot::findOrFail($data['plot_id']);

        if ($user->role === 'cooperative_manager' && $plot->cooperative_id !== $user->managedCooperative?->id) {
            throw ValidationException::withMessages([
                'plot_id' => ["Cette parcelle n'appartient pas à votre coopérative."],
            ]);
        }

        if (! empty($data['farmer_id'])) {
            $farmer = Farmer::find($data['farmer_id']);
            if (! $farmer || $farmer->cooperative_id !== $plot->cooperative_id) {
                throw ValidationException::withMessages([
                    'farmer_id' => ["Cet agriculteur n'appartient pas à la coopérative de la parcelle."],
                ]);
            }
        }

        return new InputUsageResource(InputUsage::create($data));
    }

    public function show(InputUsage $inputUsage): InputUsageResource
    {
        $this->authorize('view', $inputUsage);

        return new InputUsageResource($inputUsage->load(['input', 'plot', 'campaign', 'farmer']));
    }

    public function update(UpdateInputUsageRequest $request, InputUsage $inputUsage): InputUsageResource
    {
        $inputUsage->update($request->validated());

        return new InputUsageResource($inputUsage);
    }

    public function destroy(InputUsage $inputUsage): Response
    {
        $this->authorize('delete', $inputUsage);

        $inputUsage->delete();

        return response()->noContent();
    }
}
