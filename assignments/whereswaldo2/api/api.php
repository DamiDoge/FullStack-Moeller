<?php
error_reporting(1);
//https://www.sitepoint.com/php-53-namespaces-basics/
//http://zetcode.com/db/mongodbphp/
//http://coreymaynard.com/blog/creating-a-restful-api-with-php/
//https://programmerblog.net/php-mongodb-tutorial/

require('../scripts/image_helper.php');

abstract class API
{
    /**
     * Property: method
     * The HTTP method this request was made in, either GET, POST, PUT or DELETE
     */
    protected $method = '';
    /**
     * Property: endpoint
     * The Model requested in the URI. eg: /files
     */
    protected $endpoint = '';
    /**
     * Property: verb
     * An optional additional descriptor about the endpoint, used for things that can
     * not be handled by the basic methods. eg: /files/process
     */
    protected $verb = '';
    /**
     * Property: args
     * Any additional URI components after the endpoint and verb have been removed, in our
     * case, an integer ID for the resource. eg: /<endpoint>/<verb>/<arg0>/<arg1>
     * or /<endpoint>/<arg0>
     */
    protected $args = array();
    /**
     * Property: file
     * Stores the input of the PUT request
     */
    protected $file = null;

    /**
     * Constructor: __construct
     * Allow for CORS, assemble and pre-process the data
     */
    public function __construct()
    {
        header("Access-Control-Allow-Orgin: *");
        header("Access-Control-Allow-Methods: *");
        header("Content-Type: application/json");
        
        $this->logger = new thelog();
        $this->logger->clear_log();

        $this->method = $_SERVER['REQUEST_METHOD'];
        $this->request_uri = $_SERVER['REQUEST_URI'];
        
        $this->logger->do_log($this->method);
        
        $this->args = explode('/', rtrim($this->request_uri, '/'));
        
        $this->logger->do_log($this->args);
        
        while ($this->args[0] != 'api.php') {
            array_shift($this->args);
        }
        array_shift($this->args);
        
        $this->endpoint = array_shift($this->args);


        if (strpos($this->endpoint, '?')) {
            list($this->endpoint,$urlargs) = explode('?', $this->endpoint);
        }
        
        $this->logger->do_log($this->args, "args array:");
        $this->logger->do_log($this->endpoint, "endpoint:");
        

        switch ($this->method) {
            case 'POST':
                $this->request = $this->_cleanInputs($_POST);
                break;
            case 'DELETE':
            case 'GET':
                $this->request = $this->_cleanInputs($_GET);
                break;
            case 'PUT':
                $this->request = $this->_cleanInputs($_GET);
                $this->file = file_get_contents("php://input");
                break;
            default:
                $this->_response('Invalid Method', 405);
                break;
        }
        
        if ($urlargs) {
            $urlargs = explode('&', $urlargs);
            for ($i=0; $i<sizeof($urlargs); $i++) {
                list($k,$v) = explode('=', $urlargs[$i]);
                $this->request[$k] = $v;
            }
        }
    }
    
    public function processAPI()
    {
        $this->logger->do_log($this->endpoint);
        if (method_exists($this, $this->endpoint)) {
            return $this->_response($this->{$this->endpoint}($this->args));
        }
        return $this->_response("No Endpoint: $this->endpoint", 404);
    }

    private function _response($data, $status = 200)
    {
        header("HTTP/1.1 " . $status . " " . $this->_requestStatus($status));
        return json_encode($data);
    }

    private function _cleanInputs($data)
    {
        $clean_input = array();
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                $clean_input[$k] = $this->_cleanInputs($v);
            }
        } else {
            $clean_input = trim(strip_tags($data));
        }
        return $clean_input;
    }

    private function _requestStatus($code)
    {
        $status = array(
            200 => 'OK',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            500 => 'Internal Server Error',
        );
        return ($status[$code])?$status[$code]:$status[500];
    }
}

class MyAPI extends API
{
    public function __construct()
    {
        parent::__construct();
        
        $this->mdb = 'waldogame';
        $this->mh = new mongoHelper($this->mdb);
        $this->mh->setDbcoll('games');
    }

    public function clear_data(){
        $this->mdb = 'waldogame';
        $this->mh = new mongoHelper($this->mdb);
        $this->mh->setDbcoll('games');
        $this->mh->delete();
    }

    public function make_game_board(){
        $args = $this->request;

        $game_id = (string)time();
        $waldo_height = 32;
        $waldo_width = 16;
        // Create instance of our image helper
        $waldoGame = new ImageHelper();

        // example resizing a waldo image
        $waldoImg = $waldoGame->resize_waldo('/var/www/html/whereswaldo2/waldo_images/waldo_walking_200x451.png', $waldo_width, $waldo_height);
    
        list($base_width,$base_height,$null1,$null2) = getimagesize('/var/www/html/whereswaldo2/images/crowd.jpg');
        
        $rx = rand(0,$base_width - $waldo_width);
        $ry = rand(0,$base_height - $waldo_height);

        $data = ['x'=>$rx,'y'=>$ry, 'walx'=>$waldo_width, 'waly'=>$waldo_height, 'game_id'=>$game_id,'image_path'=>'/var/www/html/whereswaldo2/game_images','img_type'=>'png'];

        // place the information into a mongoDB
        $this->mh->insert([$data]);
    
        // open up the camping image and make the white background transparent (not awesome)
        $waldoGame->make_transparent('/var/www/html/whereswaldo2/waldo_images/waldo_camping_537x429.jpg', [0,0,0], 'camping_transparent.png', '/var/www/html/whereswaldo2/game_images');
    
        // example resizing a waldo image
        $waldoImg = $waldoGame->resize_waldo('waldo_walking_200x451.png', 16, 32, 'waldo_resized', '/var/www/html/whereswaldo2/game_images');
    
        // put a single waldo on another image
        $waldoGame->place_waldo('/var/www/html/whereswaldo2/images/crowd.jpg', $waldoImg, 16, 32, $rx, $ry, (string)$game_id, '/var/www/html/whereswaldo2/game_images');
        // return game id so that index knows what game
        return $game_id;
    }

    public function check_click()
    {
        $this->mh->setDbcoll('games');
        if(isset($_GET['gameID']) && isset($_GET['xcoord']) && isset($_GET['ycoord']))
        {
            $gameID = $_GET['gameID'];
            $xcoord = $_GET["xcoord"];
            $ycoord = $_GET["ycoord"];
            //find the game in question
            $checkgame = $this->mh->query(['game_id' => $gameID]);
            //see if the click was within the "waldo box"
            if($checkgame[0]->x < $xcoord && $xcoord < ($checkgame[0]->x + $checkgame[0]->walx) && $checkgame[0]->y < $ycoord && $ycoord < ($checkgame[0]->y + $checkgame[0]->waly)){
                return true;
            }
        }
        return false;
    }

    protected function waldo_distance()
    {
        $this->mh->setDbcoll('games');
        if(isset($_GET['gameID']) && isset($_GET['xcoord']) && isset($_GET['ycoord']))
        {
            $gameID = $_GET['gameID'];
            $xcoord = $_GET["xcoord"];
            $ycoord = $_GET["ycoord"];

            //find the game in question     
            $checkgame = $this->mh->query(['game_id' => $gameID]);
        
            if($checkgame == NULL)
            {
                return false;
            }
            $xdis = $checkgame[0]->x - $xcoord;
            $ydis = $checkgame[0]->y - $ycoord;
        
            $dis = sqrt(pow($xdis, 2) + pow($ydis, 2));
            return $dis;
        }
        return false;
    }



    /////////////////////////////////////////////////////
    private function flatten_array($array){
        foreach($array as $key => $value){
            //If $value is an array.
            if(is_array($value)){
                //We need to loop through it.
                $this->flatten_array($value);
            } else{
                //It is not an array, so print it out.
                $this->temparray[$key] = $value;
            }
        }
    }

    private function addPrimaryKey($data, $coll, $key)
    {
        $max_id = $this->mh->get_max_id($this->mdb, $coll, $key);
        if ($this->has_string_keys($data)) {
            if (!array_key_exists($data, $key)) {
                $data[$key] = $max_id;
            }
        } else {
            foreach ($data as $row) {
                if (!array_key_exists($data, $key)) {
                    $data[$key] = $max_id;
                    $max_id++;
                }
            }
        }
        return $data;
    }
     
     
    private function isAssoc(array $arr)
    {
        if (array() === $arr) {
            return false;
        }
        return array_keys($arr) !== range(0, count($arr) - 1);
    }
    
    private function has_string_keys(array $array)
    {
        return count(array_filter(array_keys($array), 'is_string')) > 0;
    }
}
function llog($stuff){
    file_put_contents('log.txt',print_r($stuff,true),FILE_APPEND);
    file_put_contents('log.txt',"\n",FILE_APPEND);
}

$api = new MyAPI();
echo $api->processAPI();

exit;
