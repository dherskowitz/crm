<?php

namespace App\Support;

class TiptapMentions
{
    /**
     * Walk a Tiptap JSON document and collect the user IDs carried by every
     * mention node, so the notification trigger can read them straight from
     * the structured content.
     *
     * @param  array<string, mixed>|null  $doc
     * @return list<int>
     */
    public static function extractUserIds(?array $doc): array
    {
        if ($doc === null) {
            return [];
        }

        $ids = [];
        self::walk($doc, $ids);

        return array_values(array_unique($ids));
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  list<int>  $ids
     */
    private static function walk(array $node, array &$ids): void
    {
        if (($node['type'] ?? null) === 'mention') {
            $id = $node['attrs']['id'] ?? null;

            if (is_numeric($id)) {
                $ids[] = (int) $id;
            }
        }

        foreach ($node['content'] ?? [] as $child) {
            if (is_array($child)) {
                self::walk($child, $ids);
            }
        }
    }
}
