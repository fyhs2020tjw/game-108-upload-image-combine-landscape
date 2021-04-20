<?php
// error_reporting(0);
function guidv4($data)
{
    assert(strlen($data) == 16);

    $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function userVerified () {
    $filename = guidv4(openssl_random_pseudo_bytes(16));

    $target_dir = "/opt/lampp/htdocs/before/";
    $done_processing_dir = "/opt/lampp/htdocs/after/";
    $uploadOk = 1;
    $imageFileType = strtolower(pathinfo($target_dir . basename($_FILES["fileToUpload"]["name"]),PATHINFO_EXTENSION));
    $target_file = $target_dir . $filename . '.' . $imageFileType;

    $check = getimagesize($_FILES["fileToUpload"]["tmp_name"]);
    if($check !== false) {
        // echo "File is an image - " . $check["mime"] . ".\n";
        $uploadOk = 1;
    } else {
        echo "File is not an image.\n";
        $uploadOk = 0;
    }

// Check file size
    if ($_FILES["fileToUpload"]["size"] > 10000000) {
        echo "Sorry, your file is too large. 10MB max";
        $uploadOk = 0;
    }

// Allow certain file formats
    if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg"
        && $imageFileType != "gif" ) {
        echo "Sorry, only JPG, JPEG, PNG & GIF files are allowed. : " . $imageFileType;
        $uploadOk = 0;
    }

// Check if $uploadOk is set to 0 by an error
    if ($uploadOk == 0) {
        echo "Sorry, your file was not uploaded.";
// if everything is ok, try to upload file
    } else {
        if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $target_file)) {
            // echo exec('rembg -o /opt/lampp/htdocs/after/'. $filename . '.' . $imageFileType .' ' . $target_file); // failed...

            // [step 2] execute python rembg by calling url with user upload image's path (before/uuidv4.jpg)
            $Content = file_get_contents('http://127.0.0.1:5000/?url=http://127.0.0.1/before/' . $filename . '.' . $imageFileType);
            file_put_contents($done_processing_dir . $filename . '.png', $Content);

            // [step 3] crop out empty white background of png file using node script https://github.com/jwitcig/image-crop.git
            file_get_contents('http://127.0.0.1:3000/crop/' . $filename);

            // [step 4] combine user uploaded png with school's landscape photos

//@see: http://php.net/manual/en/function.imagecopymerge.php for below function in first comment
            function imagecopymerge_alpha($dst_im, $src_im, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h, $pct)
            {
                // creating a cut resource
                $cut = imagecreatetruecolor($src_w, $src_h);

                // copying relevant section from background to the cut resource
                imagecopy($cut, $dst_im, 0, 0, $dst_x, $dst_y, $src_w, $src_h);

                // copying relevant section from watermark to the cut resource
                imagecopy($cut, $src_im, 0, 0, $src_x, $src_y, $src_w, $src_h);

                // insert cut resource to destination image
                imagecopymerge($dst_im, $cut, $dst_x, $dst_y, 0, 0, $src_w, $src_h, $pct);
            }

            $landscape = imagecreatefrompng('./confucius.png');
            // transparent
            $userUploaded = imagecreatefrompng($done_processing_dir . $filename . '-cropped.png');
            list($width, $height) = getimagesize($done_processing_dir . $filename . '-cropped.png');
            $userUploaded = imagescale($userUploaded, $width / 2);
            //Adjust paramerters according to your image
            imagecopymerge_alpha($landscape, $userUploaded, 200, 460, 0, 0, $width / 2, ($height / 2) - 5, 100);

            header('Content-Type: image/png');
            imagepng($landscape);
            // [END step4]
        } else {
            echo "Sorry, there was an error uploading your file.";
        }
    }
}

if (isset($_POST["submit"]) && empty($_POST['h-captcha-response'])) userVerified();
    ////////////echo "Please complete Captcha challenge.";

if(isset($_POST["submit"]) && !empty($_POST['h-captcha-response'])) {

    $data = array(
        'secret' => "0x802528Ff92C74E370027E778aC82c09788e13FAa",
        'response' => $_POST['h-captcha-response']
    );
    $verify = curl_init();
    curl_setopt($verify, CURLOPT_URL, "https://hcaptcha.com/siteverify");
    curl_setopt($verify, CURLOPT_POST, true);
    curl_setopt($verify, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($verify, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($verify);
// var_dump($response);
    $responseData = json_decode($response);
    if($responseData->success) {
        // your success code goes here
        //////////////////// userVerified();
    }
    else {
        echo "Human verification failed.";
    }
} else {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <script src="https://hcaptcha.com/1/api.js" async defer></script>
    </head>
    <body>

    <form action="<?php echo basename(__FILE__) ?>" method="post" enctype="multipart/form-data">
        Select image to upload:
        <input type="file" name="fileToUpload" id="fileToUpload">
        <div class="h-captcha" data-sitekey="f28a2818-82f6-44b5-9937-8e4d3cf4c856"></div>
        <input type="submit" value="Upload Image" name="submit">
    </form>

    </body>
    </html>
    <?php
}
?>