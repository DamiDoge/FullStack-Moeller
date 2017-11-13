<?php

require("mongo_helper.php");

class ImageHelper
{
    public function __construct()
    {
        $this->waldo_images    = "/var/www/html/waldo/waldo_images/";
        $this->scene_images    = "/var/www/html/waldo/images/";
        $this->save_directory  =  "/var/www/html/waldo/images/saved/";
    }

    /**
     * place_waldo
     *     Puts one image over an another at a given x,y location.
     * @Params:
     *     $base_image (resource, string): image resource or string path to image
     *     $waldo      (resource, string): image resource or string path to image
     *     $width                   (int): width of waldo
     *     $height                  (int): height of waldo
     *     $x                       (int): x coord to place waldo on base image
     *     $y                       (int): y coord to place waldo on base image
     *     $new_name             (string): new name to give saved image
     * @Returns:
     *     new image with waldo (resource)
     * @Usage:
     *     // open a waldo image and resize it to 16x34
     *     $waldo = prepare_waldo('waldo_walking_200x451.png',16,34,true,'hope');
     *     // get image size of crowd.jpg
     *     list($width, $height, $type, $attr) = getimagesize ('../images/crowd.jpg');
     *     // get random location between 0 and width and height
     *     $rx = rand(0,$width);
     *     $ry = rand(0,$height);
     *     // place waldo on crowd image at random place
     *     $base = place_waldo('../images/crowd.jpg',$waldo,16,34,$rx,$ry,true,"../images/crowd_waldo.png");
     */
    function place_waldo($base_image, $waldo, $width, $height, $x = 0, $y = 0, $new_name = null, $path = null)
    {
        
        // Turn it into a GD resource if necessary.
        $base_image = $this->to_resource($base_image);

        // $waldo = $this->clone_img_resource($waldo);
        // var_dump($waldo);

        imagesavealpha($waldo, false);
        imagealphablending($waldo, false);
        imagecopy($base_image, $waldo, $x, $y, 0, 0, $width, $height);
        if ($new_name) {
            $success = $this->save_image($base_image, $path, $new_name);
        }
        return $base_image;
    }

    /**
     * resize_waldo
     *     Opens one of the waldo images and resizes it and makes sure the background is transparent
     * @Params:
     *     $file_name (resource, string): image resource or string path to image
     *     $w                      (int): width to make waldo
     *     $h                      (int): height to make waldo
     *     $save                   (bool): save image to disk
     *     $new_name             (string): new name to give saved image
     *     $path                 (string): path to save at
     * @Returns:
     *     new image with waldo (resource)
     * @Usage:
     *     // open a waldo image and resize it to 16x34
     *     $waldo = resize_waldo('waldo_walking_200x451.png',16,34,true,'hope');
     */
    public function resize_waldo($file_name, $w = null, $h = null, $new_name = null, $path = null)
    {

        //check to see of waldo exists in our directory
        $waldos = scandir($this->waldo_images);
        if (!in_array($file_name, $waldos)) {
            return false;
        }

        if (!$w || !$h) {
            list($width, $height, $type, $attr) = getimagesize($this->waldo_images.$file_name);
            echo"$width,$height\n";
            if (!$w) {
                if ($width > $height) {
                    $w = round(($width/$height) * $h);
                } else {
                    $w = round(($height/$width) * $h);
                }
                echo"w=$w\n";
            } else {
                if ($height > $width) {
                    $h = round(($width/$height) * $w);
                } else {
                    $h = round(($height/$width) * $w);
                }
                echo"h=$h\n";
            }
        }

        // Turn filename into a GD resource if necessary
        $img = $this->to_resource($this->waldo_images.$file_name);

        // resize image to wxh
        $resized = imagescale ($img, $w, $h);

        // make sure that the new waldo has transparent background
        $transparent = $this->make_transparent($resized, [0,0,0]);

        // save if you want
        if ($new_name) {
            $success = $this->save_image($transparent, $path, $new_name);
        }
        return $transparent;
    }
    /**
     * @Params:
     *     $in_file (string)  : the name of the file to give transparent background
     *     $color   (array)   : array(r,g,b)
     *     $new_name (string) : new name to give saved image
     *     $path (string)     : location to save image
     * @Returns:
     *     new image with transparent background (resource)
     */
    public function make_transparent($in_file, $color, $new_name = null, $path = null)
    {

        // Turn it into a GD resource if necessary.
        $img = $this->to_resource($in_file);
        
        // allocate a GD color resource with the RGB passed in via $color.
        $removeColour = imagecolorallocate($img, (int)$color[0], (int)$color[1], (int)$color[2]);

        // Define a color as transparent
        imagecolortransparent($img, $removeColour);

        // Set the flag to save full alpha channel on this image
        imagesavealpha($img, true);

        // Allocate a color for an image using black (0,0,0) as the transparent color.
        // The last value (127) can be from 0-127 where 127 is completely opaque and 0
        // is solid color
        $transColor = imagecolorallocatealpha($img, 0, 0, 0, 127);

        // not sure if we need this, testing to follow
        imagefill($img, 0, 0, $transColor);
        if ($new_name) {
            $this->save_image($img, $path, $new_name);
        }
        
        return $img;
    }

    /**
     * to_resource: turns a string path into a new image resource unless its already a resource.
     * @Params:
     *     $image (string,resource) : the name of the file or resource to check
     *     $path (string) : path to find file it it's not a resource
     * @Returns:
     *     (resource) : image resource, either a new one, or the one passed in
     */
    private function to_resource($image, $path = null)
    {
        if (gettype ($image) == 'resource') {
            return $image;
        } else {
            if ($path) {
                $path = $this->fix_path($path);
                $image = $path.$image;
            }
            if ($this->image_type($image) == 'png') {
                return imagecreatefrompng($image);
            } elseif ($this->image_type($image) == 'jpg') {
                return imagecreatefromjpeg($image);
            } elseif ($this->image_type($image) == 'bmp') {
                return imagecreatefrombmp($image);
            } elseif ($this->image_type($image) == 'gif') {
                return imagecreatefromgif($image);
            }
        }
        return null;
    }

    public function clone_img_resource($img)
    {
        
        //Get width from image.
        $w = imagesx($img);

        //Get height from image.
        $h = imagesy($img);

        //Get the transparent color from a 256 palette image.
        $trans = imagecolortransparent($img);
        
          //If this is a true color image...
        if (imageistruecolor($img)) {
            $clone = imagecreatetruecolor($w, $h);
            imagealphablending($clone, false);
            imagesavealpha($clone, true);
        } //If this is a 256 color palette image...
        else {
            $clone = imagecreate($w, $h);
        
            //If the image has transparency...
            if ($trans >= 0) {
                $rgb = imagecolorsforindex($img, $trans);
        
                imagesavealpha($clone, true);
                $trans_index = imagecolorallocatealpha($clone, $rgb['red'], $rgb['green'], $rgb['blue'], $rgb['alpha']);
                imagefill($clone, 0, 0, $trans_index);
            }
        }
        
          //Create the Clone!!
          imagecopy($clone, $img, 0, 0, 0, 0, $w, $h);
          var_dump($clone);
          return $clone;
    }

    /**
     * @Params:
     *     $in_file (string) : the name of the file to check
     * @Returns:
     *     file_type (string)
     */
    private function image_type($in_file)
    {
        switch (exif_imagetype($in_file)) {
            case 1:
                return 'gif';
            case 2:
                return 'jpg';
            case 3:
                return 'png';
            case 6:
                return 'bmp';
        }
        return null;
    }

    /**
     * @Params:
     *     $in_file (string) : the name of the file to convert
     *     $type (string)    : the type (png,jpg,etc)
     * @Returns:
     *     out_file (string) : name of converted file
     */
    private function image_convert($in_file, $type)
    {
        $parts = explode('.', $in_file);
        $ext = array_pop($parts);
        $out_file = implode('.', $parts);
        //echo "convert {$in_file} {$out_file}.{$type}\n";
        $out_file = "{$out_file}.{$type}";
        exec("convert {$in_file} {$out_file}");
        return $out_file;
    }

    /**
     * @Params:
     *     $name (string)  : the name of the file to save
     *     $img (resource) : resource to save
     * @Returns:
     *     success (bool) : true if file saved
     */
    public function save_image($img, $path = null, $name = null)
    {
        if (!$name) {
            $name = (string)time();
        } else {
            if (strpos($name, '.')) {
                list($name,$ext) = explode('.', $name);
            } else {
                $ext = 'png';
            }
        }
        if (!$path) {
            $path = $this->save_directory;
        } else {
            $path = $this->fix_path($path);
        }
        imagepng($img, "{$path}{$name}.{$ext}");
        return file_exists("{$path}{$name}.{$ext}");
    }

    /**
     * fix_path: simply makes sure a slash is on end of string
     * @Params:
     *     $path (string) : the path
     * @Returns:
     *     (string): new path with slash
     */
    private function fix_path($path)
    {
        // these lines ensure a slash is on end of path
        $path = rtrim($path, "/");
        $path .= "/";
        return $path;
    }

    public function color_waldo($img,$old,$new,$new_name = null, $path = null){

        $im = $this->to_resource($img,$path);
        imagetruecolortopalette($im, false, 255);

        $index = imagecolorclosest ( $im, $old[0],$old[1],$old[2]); // get White COlor
        imagecolorset($im,$index,$new[0],$new[1],$new[2]); // SET NEW COLOR
        $this->save_image($im, $path, $new_name);
    }
}

if ($argv[1] == 'run_image_tests') {
    echo"Running tests...\n";

    // Create instance of our image helper
    $waldoGame = new ImageHelper();

    // open up the camping image and make the white background transparent (not awesome)
    $waldoGame->make_transparent('/var/www/html/waldo/waldo_images/waldo_camping_537x429.jpg', [0,0,0], 'camping_transparent.png', '/var/www/html/waldo/scripts/test_output');

    // example resizing a waldo image
    $waldoImg = $waldoGame->resize_waldo('waldo_walking_200x451.png', 16, 32, 'waldo_resized', '/var/www/html/waldo/scripts/test_output');

    // put a single waldo on another image
    $waldoGame->place_waldo('/var/www/html/waldo/images/crowd.jpg', $waldoImg, 16, 32, 100, 100, 'single_waldo_on_background', '/var/www/html/waldo/scripts/test_output');

    // initialize some vars
    $waldo_width = 17;
    $waldo_height = 35;

    //need size of base image so we can generate random locations
    list($base_width,$base_height,$null1,$null2) = getimagesize('/var/www/html/waldo/images/crowd.jpg');

    //get a waldo image resource without saving it to a file
    $waldoImg = $waldoGame->resize_waldo('waldo_walking_200x451.png', $waldo_width, $waldo_height);

    // path to image where we will place our waldos
    $base = '/var/www/html/waldo/images/crowd.jpg';
    $max = 2;
    $name = "{$max}_waldos";

    //array of waldo resources
    $waldos = [];

    // load array with copies of waldo and flip half of them
    for ($i=0; $i<$max; $i++) {
        $waldos[$i] = $waldoGame->clone_img_resource($waldoImg);
        if ($i % 2 == 0) {
            imageflip($waldos[$i], IMG_FLIP_HORIZONTAL);
        }
    }

    // put our waldos on the base image
    for ($i=0; $i<$max; $i++) {
        $rx = rand(0, $base_width);
        $ry = rand(0, $base_height);
        echo"Putting another waldo at x:{$rx} y:{$ry}\n";
        $base = $waldoGame->place_waldo($base, $waldos[$i], $waldo_width,$waldo_height, $rx, $ry);

    }

    // save the base image with all the waldos on it
    $waldoGame->save_image($base, '/var/www/html/waldo/scripts/test_output', $name);
    $waldoGame->color_waldo('waldo_camping_537x429.jpg',[214,24,52],[14,66,115],'waldo_camping_blue_537x429.jpg', '/var/www/html/waldo/waldo_images/');
}
