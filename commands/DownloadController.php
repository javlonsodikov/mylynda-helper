<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace app\commands;

use yii\console\Controller;


class DownloadController extends Controller
{
    private $uagent = "Mozilla/5.0 (Windows NT 10.0; WOW64; rv:46.0) Gecko/20100101 Firefox/46.0";

    public function actionIndex($url, $login, $pass)
    {
        //echo $this->login($login, $pass);
        $content = $this->getContent($url);
        file_put_contents(__DIR__ . "/test.html", $content);
        $data = $this->extractData($content, "/\<a href=\"(.*)\" class=\"item-name video-name ga\"/i");
        //print_r($data);
        $path = parse_url($url, PHP_URL_PATH);
        $topics = explode("/", $path);
        $topics = $topics[1];
        if (!empty($data)) {
            foreach ($data as $item) {

                $item = rtrim($item, " ? ");
                $vdata = $this->getContent($item);

                $var = $this->extractData($vdata, '/data-conviva="(.*)\"/i');
                $remote_file = $this->getJson($var[0], 'Url');
                $videoTitle = $this->getJson($var[0], 'VideoTitle');
                $courseTitle = $this->getJson($var[0], 'CourseTitle');
                $courseTitle = preg_replace("/[^a-zA-Z0-9\_\-\. ]/", "", $courseTitle);
                $courseTitle = str_replace(" ", "-", $courseTitle);
                $path = 'd:/Downloads/Lynda/' . $topics . '/' . $courseTitle . '/';
                //echo "Path:" . $path . PHP_EOL;
                @mkdir($path, 0777, true);

                //echo $videoUrl . " " . $videoTitle . PHP_EOL;
                //echo PHP_EOL. " :" . $file_name . PHP_EOL;
                //continue;

                $filename = parse_url($remote_file, PHP_URL_PATH);
                $filename = pathinfo($filename, PATHINFO_BASENAME);
                $ext = pathinfo($filename, PATHINFO_EXTENSION);
                //$file_name_title = preg_replace("/[^a-zA-Z0-9\_\-\. ]/", "", $videoTitle) . "." . $ext;
                echo "Checking: " . $path . $filename . PHP_EOL;
                /*if (file_exists($path . $file_name_title)) {
                    rename($path . $file_name_title, $path . $filename);
                }*/

                if (file_exists($path . $filename)) {
                    $remoteFileSize = $this->retrieveRemoteFileSize($remote_file);
                    $localFileSize = filesize($path . $filename);
                    echo "Remote x Local : " . $remoteFileSize . " x " . $localFileSize;
                    if ($remoteFileSize != $localFileSize) {
                        echo " Resuming ..." . PHP_EOL;
                        if (!$this->saveContent($remote_file, $path . $filename, $localFileSize)) {

                        }
                    } else {
                        echo " Ok!" . PHP_EOL;
                    }
                } else {
                    echo "Downloading: " . $path . $filename . PHP_EOL;
                    $this->saveContent($remote_file, $path . $filename);
                }
                echo PHP_EOL;
            }
        }

        return 0;

    }

    private function getContent($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->uagent);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_ENCODING, "gzip");
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_COOKIESESSION, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, __DIR__ . "/cookie.txt");
        curl_setopt($ch, CURLOPT_COOKIEFILE, __DIR__ . "/cookie.txt");

        $content = curl_exec($ch);
        if (curl_errno($ch)) {
            return curl_error($ch);
        }
        curl_close($ch);
        return $content;
    }

    private function extractData($html, $pattern)
    {
        preg_match_all($pattern, $html, $data);
        return $data[1];
    }

    private function getJson($var, $item)
    {
        $var = str_replace(array("data-conviva=\"", "\""), "", $var);
        $data = html_entity_decode($var);
        $data = json_decode($data);
        return $data->{$item};
    }

    private function retrieveRemoteFileSize($url)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, TRUE);
        curl_setopt($ch, CURLOPT_NOBODY, TRUE);

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->uagent);
        curl_setopt($ch, CURLOPT_ENCODING, "gzip");
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_COOKIESESSION, true);

        $data = curl_exec($ch);
        $size = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
        if (curl_errno($ch)) {
            echo curl_error($ch);
        }
        curl_close($ch);
        return $size;
    }

    private
    function saveContent($url, $file, $from = 0)
    {


        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->uagent);
        curl_setopt($ch, CURLOPT_ENCODING, "gzip");
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_COOKIESESSION, true);
        if ($from > 0) {
            curl_setopt($ch, CURLOPT_RANGE, $from . "-");
            $out = fopen($file, "a");
        } else {
            $out = fopen($file, "wb");
        }

        if ($out == FALSE) {
            echo "File not opened $file" . PHP_EOL;
            return;
        }

        curl_setopt($ch, CURLOPT_FILE, $out);

        $content = curl_exec($ch);
        if (curl_errno($ch)) {
            echo curl_error($ch);
            return false;
        }
        curl_close($ch);
        fclose($out);
        return true;
    }

    private
    function login($login, $pass)
    {
        return $this->loginUrl("https://www.lynda.com/login/login.aspx", $login, $pass);

    }

    private
    function loginUrl($url, $login, $pass)
    {
        /*$fields_string = "";
        $fields = array(
            "usernameInput" => urlencode($login),
            "passwordInput" => urlencode($pass),
            "rememberInput" => urlencode("on"),
            "log+in" => urlencode("Log in"),
            "username" => urlencode($login),
            "password" => urlencode($pass),
            "remember" => true,
            "stayPut" => false,
            "linkedInOAuth" => "",
            "socialLoginId" => "",
            "timestamp" => "",
            "signature" => "",
            "fromUrl" => urlencode("https://www.lynda.com/login/login.aspx"),
            "redirectTo" => "");
        foreach ($fields as $key => $value) {
            $fields_string .= $key . '=' . $value . '&';
        }
        rtrim($fields_string, '&');*/
        $username = urlencode($login);
        $password = urlencode($pass);
        //echo $fields_string;
        $fields_string = "usernameInput=" . $username . "&passwordInput=" . $password . "&rememberInput=on&log+in=Log+in&username=" . $username . "&password=" . $password . "&remember=true&stayPut=false&linkedInOAuth=&socialLoginId=&timestamp=&signature=&fromUrl=https%3A%2F%2Fwww.lynda.com%2Flogin%2Flogin.aspx&redirectTo=";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->uagent);
        curl_setopt($ch, CURLOPT_ENCODING, "gzip");
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_COOKIESESSION, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, __DIR__ . "/cookie.txt");
        curl_setopt($ch, CURLOPT_COOKIEFILE, __DIR__ . "/cookie.txt");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);

        $content = curl_exec($ch);
        if (curl_errno($ch)) {
            return curl_error($ch);
        }
        curl_close($ch);
        return $content;
    }
}
