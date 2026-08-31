<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\FolderCycleException;
use App\Exceptions\FolderDepthException;
use App\Http\Controllers\Controller;
use App\Http\Requests\MoveFolderRequest;
use App\Http\Requests\StoreFolderRequest;
use App\Http\Requests\UpdateFolderRequest;
use App\Models\Folder;
use App\Services\FolderService;
use Illuminate\Http\RedirectResponse;

class FolderController extends Controller
{
    public function __construct(private FolderService $service) {}

    public function store(StoreFolderRequest $request): RedirectResponse
    {
        try {
            $this->service->create(
                $request->user(),
                $request->validated('name'),
                $request->validated('parent_id'),
            );
        } catch (FolderDepthException $e) {
            return back()->withErrors(['parent_id' => $e->getMessage()]);
        }

        return back()->with('flash', ['kind' => 'saved']);
    }

    public function update(UpdateFolderRequest $request, Folder $folder): RedirectResponse
    {
        $this->authorize('update', $folder);

        $folder->update(['name' => $request->validated('name')]);

        return back()->with('flash', ['kind' => 'updated']);
    }

    public function move(MoveFolderRequest $request, Folder $folder): RedirectResponse
    {
        $this->authorize('update', $folder);

        try {
            $this->service->move($folder, $request->validated('parent_id'));
        } catch (FolderDepthException|FolderCycleException $e) {
            return back()->withErrors(['parent_id' => $e->getMessage()]);
        }

        return back()->with('flash', ['kind' => 'updated']);
    }

    public function destroy(Folder $folder): RedirectResponse
    {
        $this->authorize('delete', $folder);

        $this->service->delete($folder);

        return back()->with('flash', ['kind' => 'deleted']);
    }
}
