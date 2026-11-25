<?php

namespace App\Users\Http\Controllers\Api;

use App\Users\Http\Controllers\Controller;
use App\Users\Http\Requests\ExportMembersRequest;
use App\Users\Http\Resources\MemberExportResource;
use App\Users\Models\MemberExport;
use App\Users\Services\MemberExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberExportController extends Controller
{
    private const LISTED = 5;

    public function __construct(private readonly MemberExportService $exports)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return MemberExportResource::collection(
            MemberExport::query()->availableTo($request->user())->latest()->limit(self::LISTED)->get(),
        );
    }

    public function store(ExportMembersRequest $request): JsonResponse
    {
        $export = $this->exports->request(
            $request->user(),
            $request->validated('fields'),
            $request->filters(),
            $request->validated('lang') ?? 'ro',
        );

        return (new MemberExportResource($export))->response()->setStatusCode(202);
    }

    public function download(Request $request, string $export): StreamedResponse
    {
        abort_unless(str($export)->isUuid(), 404);
        $record = MemberExport::query()->availableTo($request->user())->findOrFail($export);
        abort_unless($record->status === MemberExport::READY, 409, 'Export is not ready.');

        return $this->exports->download($record);
    }
}
