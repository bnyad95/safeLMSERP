<?php

namespace App\Services;

use App\Models\CourseMaterial;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CourseMaterialService
{
    public function uploadMaterial(int $courseId, array $data, ?int $sectionId = null): CourseMaterial
    {
        $filePath = null;
        if (isset($data['file'])) {
            $filePath = $data['file']->store('course-materials', 'public');
        }

        return CourseMaterial::create([
            'course_id' => $courseId,
            'course_section_id' => $sectionId,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'file_path' => $filePath,
            'file_type' => $data['file_type'] ?? 'other',
            'visibility' => $data['visibility'] ?? 'draft',
            'uploaded_by' => Auth::id(),
        ]);
    }

    public function updateMaterial(CourseMaterial $material, array $data): CourseMaterial
    {
        $updateData = [
            'title' => $data['title'] ?? $material->title,
            'description' => $data['description'] ?? $material->description,
            'file_type' => $data['file_type'] ?? $material->file_type,
            'visibility' => $data['visibility'] ?? $material->visibility,
        ];

        if (isset($data['file'])) {
            if ($material->file_path) {
                Storage::disk('public')->delete($material->file_path);
            }
            $updateData['file_path'] = $data['file']->store('course-materials', 'public');
        }

        $material->update($updateData);

        return $material;
    }

    public function deleteMaterial(CourseMaterial $material, bool $force = false): bool
    {
        if ($material->file_path) {
            Storage::disk('public')->delete($material->file_path);
        }

        if ($force) {
            return $material->forceDelete();
        }

        return $material->delete();
    }

    public function publishMaterial(CourseMaterial $material): CourseMaterial
    {
        $material->update(['visibility' => 'published']);

        return $material;
    }

    public function unpublishMaterial(CourseMaterial $material): CourseMaterial
    {
        $material->update(['visibility' => 'draft']);

        return $material;
    }

    public function getMaterials(int $courseId, bool $onlyPublished = true, ?int $sectionId = null)
    {
        $query = CourseMaterial::where('course_id', $courseId);

        if ($sectionId) {
            $query->where(function ($query) use ($sectionId) {
                $query->where('course_section_id', $sectionId)
                    ->orWhereNull('course_section_id');
            });
        }

        if ($onlyPublished) {
            $query->where('visibility', 'published');
        }

        return $query->orderBy('created_at', 'desc')->get();
    }
}
