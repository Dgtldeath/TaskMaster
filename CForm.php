<?php

/**
 * Created by PhpStorm.
 * User: adamgumm
 * Date: 7/29/20
 * Time: 4:25 PM
 */
class CForm
{

    public function createTextInput($label, $name, $value = "") {
        return '<div class="form-group">
                    <label for="'.$name.'">'.$label.':</label>
                    <input type="text" class="form-control form-control-user mb-2" name="'.$name.'" id="'.$name.'" value="'.$value.'"/>
                </div>';
    }

    public function createFileInput($label, $name) {
        return '<div class="form-group">
                    <label for="'.$name.'">'.$label.':</label>
                    <input type="file" class="mb-2" name="'.$name.'" id="'.$name.'" />
                </div>';
    }

    public function createSubmitButton($name, $value = "") {
        return '<div class="form-group">
                    <input type="submit" class="btn btn-primary btn-user btn-block mt-2" name="'.$name.'" id="'.$name.'" value="'.$value.'"/>
                </div>';
    }

    public function createBannerFromSaveResponse($response) {
        if($response == 1) {
            return '<div class="row">
                        <div class="col-lg-12 mb-4 alert-success">
                            Save <strong>Successful</strong>
                    </div>';
        }
        else if($response == 0) {
            return '<div class="row">
                        <div class="col-lg-12 mb-4 alert-danger">
                            Save <strong>Error</strong>
                    </div>';
        }
        else {
            return '<div class="row">
                        <div class="col-lg-12 mb-4 alert-warning">
                            An <strong>unknown error</strong> occurred.
                    </div>';
        }
    }
}