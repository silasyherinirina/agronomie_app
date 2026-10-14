<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\HandlesReferentialIntegrity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Input\StoreInputRequest;
use App\Http\Requests\Input\UpdateInputRequest;
use App\Http\Resources\InputResource;
use App\Models\Input;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InputController extends Controller
{
    use HandlesReferentialIntegrity;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Input::class);

        $query = Input::query();

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        if ($search = $request->query('search')) {
            $query->where('name', 'ilike', "%{$search}%");
        }

        return InputResource::collection(
    $query
        ->orderByDesc('updated_at')
        ->orderByDesc('id')
        ->paginate($this->perPage($request))
);
    }

    public function store(StoreInputRequest $request): InputResource
    {
        return new InputResource(Input::create($request->validated()));
    }

    public function show(Input $input): InputResource
    {
        $this->authorize('view', $input);

        return new InputResource($input);
    }

    public function update(UpdateInputRequest $request, Input $input): InputResource
    {
        $input->update($request->validated());

        return new InputResource($input);
    }

    public function destroy(Input $input): JsonResponse
    {
        $this->authorize('delete', $input);

        return $this->deleteOrConflict(
            fn () => $input->delete(),
            'Impossible de supprimer cet intrant : il est encore référencé par des utilisations enregistrées.'
        );
    }
}
