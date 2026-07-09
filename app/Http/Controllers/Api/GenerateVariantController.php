<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\SourceImageNotFoundException;
use App\Exceptions\UnsupportedImageException;
use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateVariantRequest;
use App\Services\GenerateVariantImage;
use Illuminate\Http\JsonResponse;

class GenerateVariantController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(GenerateVariantRequest $request, GenerateVariantImage $service): JsonResponse
    {
        $validated = $request->validated();

        try {
            $variants = $service->generate(
                disk: $validated['disk'],
                sourcePath: $validated['path'],
                mimeType: $validated['mime_type'] ?? null,
                fileName: $validated['file_name'] ?? null,
                only: $validated['variants'] ?? null,
                overwrite: $validated['overwrite'] ?? false,
            );
        } catch (SourceImageNotFoundException $exception) {
            return response()->json(['message' => $exception->getMessage()], JsonResponse::HTTP_NOT_FOUND);
        } catch (UnsupportedImageException $exception) {
            return response()->json(['message' => $exception->getMessage()], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($variants === []) {
            return response()->json([
                'message' => 'No variants were generated.',
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'data' => [
                'disk' => $validated['disk'],
                'source_path' => $validated['path'],
                'variants' => $variants,
                'generated_count' => count($variants),
            ],
        ]);
    }
}
