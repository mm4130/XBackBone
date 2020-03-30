<?php


namespace App\Database\Queries;

use App\Database\DB;
use PDO;

class TagQuery
{
    const PER_MEDIA_LIMIT = 10;

    /**
     * @var DB
     */
    private $db;

    public function __construct(DB $db)
    {
        $this->db = $db;
    }

    /**
     * @param  string  $tagName
     * @param $mediaId
     * @return array [id, limit]
     */
    public function addTag(string $tagName, $mediaId)
    {
        $tag = $this->db->query('SELECT * FROM `tags` WHERE `name` = ? LIMIT 1', $tagName)->fetch();

        $connectedIds = $this->db->query('SELECT `tag_id` FROM `uploads_tags` WHERE `upload_id` = ?', $mediaId)->fetchAll(PDO::FETCH_COLUMN, 0);

        if (!$tag && count($connectedIds) < self::PER_MEDIA_LIMIT) {
            $this->db->query('INSERT INTO `tags`(`name`) VALUES (?)', strtolower($tagName));

            $tagId = $this->db->getPdo()->lastInsertId();

            $this->db->query('INSERT INTO `uploads_tags`(`upload_id`, `tag_id`) VALUES (?, ?)', [
                $mediaId,
                $tagId,
            ]);

            return [$tagId, false];
        }

        if (count($connectedIds) >= self::PER_MEDIA_LIMIT || in_array($tag->id, $connectedIds)) {
            return [null, true];
        }

        $this->db->query('INSERT INTO `uploads_tags`(`upload_id`, `tag_id`) VALUES (?, ?)', [
            $mediaId,
            $tag->id,
        ]);

        return [$tag->id, false];
    }

    /**
     * @param $tagId
     * @param $mediaId
     * @return bool
     */
    public function removeTag($tagId, $mediaId)
    {
        $tag = $this->db->query('SELECT * FROM `tags` WHERE `id` = ? LIMIT 1', $tagId)->fetch();

        if ($tag) {
            $this->db->query('DELETE FROM `uploads_tags` WHERE `upload_id` = ? AND `tag_id` = ?', [
                $mediaId,
                $tag->id,
            ]);

            if ($this->db->query('SELECT COUNT(*) AS `count` FROM `uploads_tags` WHERE `tag_id` = ?', $tag->id)->fetch()->count == 0) {
                $this->db->query('DELETE FROM `tags` WHERE `id` = ? ', $tag->id);
            }

            return true;
        }

        return false;
    }
}
