<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'entity_type' => ['required', 'string', 'max:100'],
            'entity_id' => ['required', 'integer'],
        ]);

        $attachments = Attachment::where('entity_type', $request->entity_type)
            ->where('entity_id', $request->entity_id)
            ->with('uploadedBy')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $attachments]);
    }

    public function show(Attachment $attachment): JsonResponse
    {
        $this->authorize('view', $attachment);
        $attachment->load('uploadedBy');
        return response()->json(['data' => $attachment]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Attachment::class);

        $request->validate([
            'entity_type' => ['required', 'string', 'max:100'],
            'entity_id' => ['required', 'integer'],
            'file' => ['required', 'file', 'max:20480'], // 20 MB
        ]);

        $file = $request->file('file');
        $path = $file->store('attachments');

        $attachment = Attachment::create([
            'entity_type' => $request->input('entity_type'),
            'entity_id' => $request->input('entity_id'),
            'original_name' => $file->getClientOriginalName(),
            'storage_path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'uploaded_by_id' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'File uploaded',
            'data' => $attachment->load('uploadedBy'),
        ], 201);
    }

    public function destroy(Attachment $attachment): JsonResponse
    {
        $this->authorize('delete', $attachment);
        Storage::delete($attachment->storage_path);
        $attachment->delete();
        return response()->json(['message' => 'Attachment deleted']);
    }

    public function download(Attachment $attachment): StreamedResponse|JsonResponse
    {
        $this->authorize('view', $attachment);

        if (!Storage::exists($attachment->storage_path)) {
            return response()->json(['message' => 'File no longer exists on storage.'], 404);
        }

        return Storage::download(
            $attachment->storage_path,
            $attachment->original_name,
            ['Content-Type' => $attachment->mime_type ?? 'application/octet-stream'],
        );
    }
}
