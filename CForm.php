<?php

/**
 * Created by PhpStorm.
 * User: adamgumm
 * Date: 7/29/20
 * Time: 4:25 PM
 */

class CForm
{
    private $javascriptInputIdentifiers = array();
    private $javascriptInputTrigger;

    public function random_color_part()
    {
        return str_pad(dechex(mt_rand(0, 255)), 2, '0', STR_PAD_LEFT);
    }

    public function generate_hexcode_color()
    {
        return $this->random_color_part() . $this->random_color_part() . $this->random_color_part();
    }

    public function createTextInput($label, $name, $value = "", $placeholder = '', $center = false)
    {
        $this->javascriptInputIdentifiers[] = $name;

        return '<div class="form-group' . ($center ? ' text-center' : '') . '">
                    <label for="' . $name . '">' . $label . ':</label>
                    <input type="text" class="' . ($center ? 'text-center ' : '') . 'form-control form-control-user mb-2" name="' . $name . '" id="' . $name . '" value="' . $value . '" placeholder="' . $placeholder . '"/>
                </div>';
    }

    public function createGenericTextInput($label): string
    {
        $name = str_replace(" ", "", $label);
        $this->javascriptInputIdentifiers[] = $name;

        return '<div class="form-group">
                    <label for="' . $name . '">' . $label . ':</label>
                    <input type="text" class="form-control form-control-user mb-2" name="' . $name . '" id="' . $name . '" value="" placeholder="enter ' . strtolower($label) . '"/>
                </div>';
    }

    public function createDatePickerInput($label, $name, $value = "")
    {
        $this->javascriptInputIdentifiers[] = $name;

        return '<div class="form-group">
                    <label for="' . $name . '">' . $label . ':</label>
                    <input type="date" class="form-control form-control-user mb-2" name="' . $name . '" id="' . $name . '" value="' . $value . '"/>
                </div>';
    }

    public function createDropDown($label, $name, $values = array(), $multiple = false, $height = '100')
    {
        $this->javascriptInputIdentifiers[] = $name;

        $output = '<div class="form-group">
                    <label for="' . $name . '">' . $label . ':</label>
                    <select class="form-control form-control-user mb-2" name="' . $name . '" id="' . $name . '" ' . ($multiple ? 'multiple="multiple" style="height: ' . $height . '"' : '') . '>';

        foreach ($values as $value => $text) {
            $output .= '<option value="' . $value . '">' . $text . '</option>';
        }

        $output .= '                        
                    </select>
                </div>';

        return $output;
    }

    public function createHiddenInput($name, $value = "")
    {
        $this->javascriptInputIdentifiers[] = $name;
        return '<input type="hidden" class="form-control form-control-user mb-2" name="' . $name . '" id="' . $name . '" value="' . $value . '"/>';
    }

    public function createCheckbox($label, $name, $on = false)
    {
        return '<div class="form-group">
                    <label for="' . $name . '">' . $label . ':</label>
                    <input type="checkbox" ' . ($on ? 'checked="checked"' : '') . ' class="form-control form-control-user mb-2" name="' . $name . '" id="' . $name . '"/>
                </div>';
    }

    public function createFileInput($label, $name)
    {
        return '<div class="form-group">
                    <label for="' . $name . '">' . $label . ':</label>
                    <input type="file" class="mb-2" name="' . $name . '" id="' . $name . '" />
                </div>';
    }

    public function createSubmitButton($name, $btnText = "", $submitInputStyle = true, $useIcon = true, $withCancelBtnHTML = ''): string
    {
        $this->javascriptInputTrigger = $name;

        if ($submitInputStyle) {
            return '<div class="form-group">
                    <input type="submit" class="btn btn-primary btn-user btn-block mt-2" name="' . $name . '" id="' . $name . '" value="' . $btnText . '"/>' . $withCancelBtnHTML . '
                </div>';
        } else {
            return '<button type="submit" class="btn btn-primary" name="' . $name . '" id="' . $name . '">' . $btnText . ($useIcon ? ' <i class="fas fa-save"></i>' : '') . '</button>' . $withCancelBtnHTML;
        }
    }

    public function createTextArea($label, $name)
    {
        return '<div class="form-group">
                    <label for="' . $name . '">' . $label . ':</label>
                    <textarea class="form-control form-control-user mb-2" name="' . $name . '" id="' . $name . '"></textarea>
                </div>';
    }

    public function createBannerFromSaveResponse($response)
    {
        if ($response == 1) {
            return '<div class="row">
                        <div class="col-lg-12 mb-4 alert-success">
                            Save <strong>Successful</strong>
                    </div>';
        } else if ($response == 0) {
            return '<div class="row">
                        <div class="col-lg-12 mb-4 alert-danger">
                            Save <strong>Error</strong>
                    </div>';
        } else {
            return '<div class="row">
                        <div class="col-lg-12 mb-4 alert-warning">
                            An <strong>unknown error</strong> occurred.
                    </div>';
        }
    }

    public function generateJavascriptFormEventHandlers(): string
    {
        $output = '';
        $actionStringParameter = '';
        $successActions = '';
        foreach ($this->javascriptInputIdentifiers as $identifier) {
            $actionStringParameter .= $identifier . ": $(\"#" . $identifier . "\").val(),";
            $successActions .= '$("#' . $identifier . '").val("");';
        }

        $output .= '<script>
                        $(function () {
                            $("#' . $this->javascriptInputTrigger . '").click(function () {
                                $.post("' . $_SERVER['PHP_SELF'] . '", { action: "' . $this->javascriptInputTrigger . '", ' . $actionStringParameter . '}, function(response) {
                                    if(response == 1) {
                                        ' . $successActions . '
                                        alert("Success!");
                                        $(".close-modal").click();
                                        setTimeout(function() {
                                        window.location.reload(false);    
                                        }, 250);
                                    }
                                    else {
                                        alert("Error with data: " + response);
                                    }
                                })
                            });
                        });
                    </script>';
        return $output;
    }

    public function getAddItemForm($inputLabels): array
    {
        $output = array();
        foreach ($inputLabels as $inputLabel) {
            $output['MainBody'] .= $this->createGenericTextInput($inputLabel);
        }
        $output['SaveBtn'] = $this->createSubmitButton('AddItem', 'Add Item', false, true);
        return $output;
    }
}
