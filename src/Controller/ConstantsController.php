<?php

namespace App\Controller;

use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

require_once __DIR__.'/../../vendor/autoload.php';

class ConstantsController
{
    private $codelisturl = "https://test-docs.peppol.eu/poacc/upgrade-3/codelist/";

    /**
     * @Route("/files/{path}")
     */
    public function download($path): Response
    {
        $content = file_get_contents("../files/" . $path)?:'File Not Found';
        $response = new Response($content);
        $response->headers->set('Content-Type', 'text/plain');
        $response->headers->set('Content-Disposition','attachment;filename="' . $path . '"');
        return $response;
    }

    /**
     * @Route("/getConstants")
     */
    public function getConstants(): Response
    {
        $list = $this->grabCodes();
        $string = $this->writeClass($list);
        return new Response($string);
    }

    private function grabCodes()
    {
        $html = file_get_contents($this->codelisturl);
        $crawler = new Crawler($html);
        $links = $crawler->filter('#main ul li a')->extract(['href']);
        $allCodes = [];
        foreach($links as $link) {
            $lhtml = file_get_contents($this->codelisturl . substr($link, strlen('../codelist/')));
            $crawler = new Crawler($lhtml);
            $crawler = $crawler->filter('.dl-horizontal');
            $identifier = null;
            $nodeBeforeIdentifier = false;
            $nodeBeforeCodes = false;
            $codeNode = null;
            foreach($crawler->children() as $node) {
                if($nodeBeforeIdentifier) {
                    $identifier = $node->textContent;
                }
                if($nodeBeforeCodes) {
                    $codeNode = $node;
                    break;
                }
                $nodeBeforeIdentifier = $node->textContent == "Identifier";
                $nodeBeforeCodes = $node->textContent == "Codes";
            }
            $codes = [];
            foreach($codeNode->childNodes as $cn) {
                if($cn->hasChildNodes()) {
                    $code = $cn->getElementsByTagName('code')[0]?$cn->getElementsByTagName('code')[0]->textContent:'';
                    $description = $cn->getElementsByTagName('strong')[0]?$cn->getElementsByTagName('strong')[0]->textContent:'';
                    $comment = $cn->getElementsByTagName('p')[0]?$cn->getElementsByTagName('p')[0]->textContent:'';
                    $comment = str_replace("\n",'',$comment);
                    $comment = preg_replace('/\s+/',' ',$comment);
                    $name = preg_replace('/(?![A-Z_])./','',str_replace(' ','_',strtoupper(trim($description))));
                    array_push($codes, ['code' => $code, 'name' => $name, 'comment' => $comment]);
                }
            }
            array_push($allCodes, ['identifier' => $identifier, 'codes' => $codes]);
        }
        return $allCodes;
    }

    private function writeClass($list, $namespace="PonderSource\Peppol\Constants")
    {
        $links = [];
        $res = "<html><body>";
        $header = <<<PHP
<?php

namespace $namespace;

PHP;
        foreach($list as $li) {
            $string = $header . <<<PHP
class {$li['identifier']}
{
PHP;
            foreach($li['codes'] as $code) {
                $comment = $code['comment']?<<<PHP
/**
     * {$code['comment']}
     **/
PHP:'';
                $string .= "\n\n" . <<<PHP
    $comment
    const {$code['name']} = "{$code['code']}";
PHP;
            }
            $string .= "\n}";
            $dir = "../files/";
            $filename = strtolower($li['identifier']) . ".php";
            $file = fopen($dir . $filename, "w");
            fwrite($file, $string);
            fclose($file);
            array_push($links, $filename);
        }
        foreach($links as $link) {
            $res .= "<a href='files/$link'>$link</a><br>";
        }
        $res .= "</body></html>";
        return $res;
    }
}