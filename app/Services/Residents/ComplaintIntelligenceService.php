<?php

namespace App\Services\Residents;

use App\Models\Complaint;

/**
 * Transparent local NLP-style baseline. Replace with an approved NLP provider when one is selected.
 * Staff review and own every final category/priority decision.
 */
class ComplaintIntelligenceService
{
    public function analyze(string $subject, string $text): array
    {
        $normalized = mb_strtolower($text);
        $rules = [
            'fire_emergency' => ['fire', 'smoke', 'burning', 'flood', 'injured', 'trapped', 'emergency', 'accident'],
            'sanitation' => ['garbage', 'trash', 'waste', 'sewage', 'drain', 'dirty', 'sanitation'],
            'peace_and_order' => ['noise', 'threat', 'violence', 'theft', 'fight', 'disturbance', 'peace'],
            'infrastructure' => ['road', 'streetlight', 'water leak', 'sidewalk', 'pothole', 'infrastructure'],
        ];

        $category = 'general';
        $matches = [];
        foreach ($rules as $candidate => $keywords) {
            $candidateMatches = array_values(array_filter($keywords, fn (string $keyword): bool => str_contains($normalized, $keyword)));
            if (count($candidateMatches) > count($matches)) {
                $category = $candidate;
                $matches = $candidateMatches;
            }
        }

        $priority = $category === 'fire_emergency' ? 'high' : (count($matches) > 0 ? 'medium' : 'low');
        $flags = [];
        if (mb_strlen(trim($text)) < 20) $flags[] = 'description_may_be_too_short';
        if (preg_match('/(.)\\1{7,}/u', $normalized)) $flags[] = 'repeated_character_anomaly';

        $duplicate = Complaint::query()
            ->where('created_at', '>=', now()->subDays(30))
            ->whereRaw('LOWER(subject) = ?', [mb_strtolower(trim($subject))])
            ->exists();
        if ($duplicate) $flags[] = 'possible_duplicate_report';

        return [
            'category' => $category,
            'priority' => $priority,
            'summary' => mb_substr(trim(preg_replace('/\\s+/', ' ', $text) ?? $text), 0, 240),
            'validation_flags' => $flags,
            'possible_duplicate' => $duplicate,
        ];
    }
}
