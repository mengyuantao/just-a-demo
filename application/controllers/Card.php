<?php
class CardController extends Yaf_Controller_Abstract {
    private $dbfile;

    private function init() {
        $this->dbfile = Yaf_Application::app()->getConfig()["application"]["sqlite"]["file"];
    }

    private static function getShowTime($time) {
        $delta = $_SERVER['REQUEST_TIME'] - $time;
        $minutes = (int) ($delta / 60);
        if ($minutes <= 0) {
            return "just now";
        } else if ($minutes < 60) {
            return "$minutes minutes ago";
        } else if ($minutes < 24 * 60) {
            $hours = (int) ($minutes / 60);
            return "$hours hours ago";
        }
        $days = (int) ($minutes / (24 * 60));
        return "$days days ago";
    }

    /**
     * example:
     * data-video="http://mvvideo2.meitudata.com/57026422677221659.mp4"
     * http://mvvideo2.meitudata.com/57026422677221659.mp4 
     *
     * data-poster="http://mvimg2.meitudata.com/57023ee9730c27409.jpg!thumb320"
     * http://mvimg2.meitudata.com/57023ee9730c27409.jpg
     */
    private static function getMeiPaiUrl($content, $attr) {
        $pos = strpos($content, $attr);
        $data = substr($content, $pos);
        $start = strpos($data, '"');
        $end = strpos($data, '"', $start + 1);
        $url = substr($data, $start + 1, ($end - $start - 1));
        $pos = strpos($url, '!');
        if ($pos === false) {
            return $url;
        }
        return substr($url, 0, $pos);
    }

    private static function getPageContent($url) {
        $ua = 'Mozilla/5.0 (iPhone; CPU iPhone OS 9_1 like Mac OS X) AppleWebKit/601.1.46 (KHTML, like Gecko) Version/9.0 Mobile/13B143 Safari/601.1';
        $ref = 'http://m.baidu.com';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERAGENT, $ua);
        curl_setopt($ch, CURLOPT_REFERER, $ref);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $data = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($httpcode >=200 && $httpcode < 300) ? $data : false;
    }

    private static function getSite($url) {
        $array = parse_url($url);
        if ($array) {
            return $array['host'];
        }
        return false;
    }

    public function indexAction() {
        $this->getView()->assign("content", "Hello World");
        $db = new SQLite3($this->dbfile, SQLITE3_OPEN_READONLY);
        $sql = "SELECT rowid, * from card order by rowid desc limit 10";
        $results = $db->query($sql);
        $cards = Array();
        $now = new DateTime();
        while ($row = $results->fetchArray()) {
            $cards[$row['uuid']]['image_url'] = $row['image_url'];
            $cards[$row['uuid']]['video_url'] = $row['video_url'];
            $cards[$row['uuid']]['page_url'] = $row['page_url'];
            $cards[$row['uuid']]['time'] = $this->getShowTime($row['insert_time']);
        }
        $this->getView()->assign("cards", $cards);
    }

    public function addAction() {
        $page_url = $_GET["page_url"];
        if (!isset($page_url) || empty(trim($page_url))) {
            $message = "page_url is empty";
            $this->getView()->assign("message", $message);
            return;
        }

        $page_url = filter_var(trim($page_url), FILTER_SANITIZE_STRING);
        $site = $this->getSite($page_url);
        if (strpos($site, "meipai.com") === false) {
            $message = "do not support this site: $site";
            $this->getView()->assign("message", $message);
            return;
        }

        $content = $this->getPageContent($page_url);
        if (!$content) {
            $message = "failed to get link: $page_url";
            $this->getView()->assign("message", $message);
            return;
        }

        $image_url = filter_var($this->getMeiPaiUrl($content, "data-poster"), FILTER_SANITIZE_STRING);
        $video_url = filter_var($this->getMeiPaiUrl($content, "data-video"), FILTER_SANITIZE_STRING);
        if (empty($image_url) || empty($video_url)) {
            $message = "failed to parse link: $page_url";
            $this->getView()->assign("message", $message);
            return;
        }

        $db = new SQLite3($this->dbfile, SQLITE3_OPEN_READWRITE);
        if (!$db) {
            $message = "failed to save link: $page_url";
            $this->getView()->assign("message", $message);
            return;
        }

        $sql = <<<EOT
INSERT INTO card
(uuid, name, user_id, caption, site, page_url, image_url, video_url, insert_time, status, is_deleted)
VALUES
(:uuid, :name, :user_id, :caption, :site, :page_url, :image_url, :video_url, :insert_time, :status, :is_deleted)
EOT;
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':uuid', uniqid(), SQLITE3_TEXT);
        $stmt->bindValue(':name', "", SQLITE3_TEXT);
        $stmt->bindValue(':caption', "", SQLITE3_TEXT);
        $stmt->bindValue(':site', $site, SQLITE3_TEXT);
        $stmt->bindValue(':page_url', $page_url, SQLITE3_TEXT);
        $stmt->bindValue(':image_url', $image_url, SQLITE3_TEXT);
        $stmt->bindValue(':video_url', $video_url, SQLITE3_TEXT);
        $stmt->bindValue(':insert_time', $_SERVER['REQUEST_TIME'], SQLITE3_INTEGER);
        $stmt->bindValue(':status',  0, SQLITE3_INTEGER);
        $stmt->bindValue(':is_deleted', 0, SQLITE3_INTEGER);
        $ret = $stmt->execute();
        if (!$ret) {
            $message = "failed to save link: $page_url";
            $this->getView()->assign("message", $message);
            return;
        }
        $db->close();

        $this->redirect("/card");
    }
}
