<?php

/**
 * Created by PhpStorm.
 * User: adamgumm
 * Date: 6/28/20
 * Time: 2:56 PM
 */
class CCategories extends CTaskDatabase
{
    public function __construct()
    {
        parent::__construct();
    }
    private $userID;
    private $tableName = "TaskMasterTaskCategories";

    /**
     * @param mixed $userID
     */
    public function setUserID($userID)
    {
        $this->userID = $userID;
    }

    public function saveCategory($POST): int
    {
        $name = mysqli_real_escape_string($this->mysqliConnectionLink, $POST['name']);
        $color = mysqli_real_escape_string($this->mysqliConnectionLink, $POST['color']);
        $query = "INSERT INTO `".$this->tableName."` (`Name`, `Color`, `UsersID`) VALUES ('".$name."', '".$color."', ".intval($this->userID).")";
        return (mysqli_query($this->mysqliConnectionLink, $query) ? 1 : 0);
    }

    public function allCategories($asTableRows = true, $asJSON = false) {
        if( intval($this->userID) == 0 )
            return 0;

        $query = "SELECT * FROM `".$this->tableName."` WHERE `UsersID` = ".$this->userID;
        $results = mysqli_query($this->mysqliConnectionLink, $query);

        if($asTableRows) {

            $output = "<tbody>";
            while ($row = mysqli_fetch_array($results)) {
                $output .= '<tr><td>'.$row['Name'].'</td><td style="background-color: '.$row['Color'].'">&nbsp;</td></tr>';
            }
            $output .= '</tbody>';

            return $output;
        }
        else if( $asJSON ) {
            $output = array();
            while ($row = mysqli_fetch_array($results)) {
                $output[] = array($row['Name'], $row['Color']);
            }
            return json_encode($output);
        }
        else {
            return 0;
        }
    }
}