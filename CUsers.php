<?php

/**
 * Created by PhpStorm.
 * User: adamgumm
 * Date: 6/11/20
 * Time: 12:42 AM
 */
class CUsers extends CTaskDatabase
{
    public $email;
    private $userID;
    private $userCategories;

    private $mainDBtable = 'TaskMasterUsers';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @return void
     */
    private function updateLastLoginTimestamp()
    {
        mysqli_query($this->mysqliConnectionLink, "UPDATE `" . $this->mainDBtable . "` SET `LastLoginTimestamp` = " . time() . " WHERE `ID` = '" . $this->userID . "' LIMIT 1");
    }

    /**
     * @param $firstname
     * @param $lastname
     * @param $email
     * @param $password
     * @return int
     */
    public function registerUser($firstname, $lastname, $email, $password): int
    {
        if ($this->userEmailExist($email) == 0 && $firstname != "" && $lastname != "" && $email != "" && $password != "") {
            if (mysqli_query($this->mysqliConnectionLink, "INSERT INTO `" . $this->mainDBtable . "` (`FirstName`, `LastName`, `Email`, `Password`) 
                                                    VALUES ('" . $this->clean($firstname, true) . "', '" . $this->clean($lastname, true) . "', '" . $this->clean($email, true) . "', '" . md5($this->clean($password, true)) . "') ")) {
                return 1;
            }

            return 0;
        }
        return 2;
    }

    /**
     * @param $email
     * @return int|string
     */
    public function userEmailExist($email)
    {
        return mysqli_num_rows(mysqli_query($this->mysqliConnectionLink, "SELECT `Email` FROM `" . $this->mainDBtable . "` WHERE `Email` = '" . $this->clean($email, true) . "'")); // if email exist, return 1, else 0
    }

    /**
     * @param $email
     * @param $password
     * @return int
     */
    public function login($email, $password): int
    {
        if ($email != "" && $password != "") {
            $query = "SELECT `ID`, `Email` FROM `" . $this->mainDBtable . "` WHERE `Email` = '" . $this->clean($email, true) . "' AND `Password` = '" . md5($this->clean($password, true)) . "' LIMIT 1";
            $result = mysqli_query($this->mysqliConnectionLink, $query);
        } else {
            return 0;
        }

        if (mysqli_num_rows($result) <= 0) {
            return 0;
        } else {
            $data = mysqli_fetch_array($result);
            $this->email = $data['Email'];
            $this->userID = $data['ID'];

            $this->updateLastLoginTimestamp();

            return 1;
        }
    }

    /**
     * @return array
     */
    public function getUserCategories(): array
    {
        $categories = array();
        $resource = mysqli_query($this->mysqliConnectionLink, "SELECT * FROM `TaskMasterTaskCategories` WHERE `UsersID` = " . intval($this->userID) . " ORDER BY `Name`");
        while ($row = mysqli_fetch_array($resource)) {
            $categories[$row['ID']] = array($row['Name'], $row['Color']);
        }
        $this->userCategories = $categories;    // set and return value
        return $this->userCategories;
    }


    /**
     * @param $userIdentifier
     * @param $API
     * @return bool
     */
    public function verifyLogin($userIdentifier, $API = false): bool
    {
        if ($API) {
            $uuid = $userIdentifier;
            if ($resource = mysqli_query($this->mysqliConnectionLink, "SELECT `ID`, `Email` FROM `" . $this->mainDBtable . "` WHERE `ID` = " . intval($uuid))) {
                $results = mysqli_fetch_array($resource);
                $this->setUserID($results['ID']);
                $this->setUserEmail($results['Email']);
                return true;
            } else {
                return false;
            }
        } else {
            $email = $userIdentifier;

            if ($email == "") {
                return false;
            }

            if ($resource = mysqli_query($this->mysqliConnectionLink, "SELECT `ID`, `Email` FROM `" . $this->mainDBtable . "` WHERE `Email` = '" . $this->clean($email, true) . "'")) {
                $results = mysqli_fetch_array($resource);
                $this->setUserID($results['ID']);
                $this->setUserEmail($results['Email']);
                return true;
            } else {
                return false;
            }
        }
    }

    public function getUserID()
    {
        return $this->userID;
    }

    public function setUserID($userID)
    {
        $this->userID = $userID;
    }

    public function setUserEmail($email)
    {
        $this->email = $email;
    }
}