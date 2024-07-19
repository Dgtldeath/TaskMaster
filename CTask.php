<?php

/**
 * Created by PhpStorm.
 * User: adamgumm
 * Date: 6/9/20
 * Time: 4:36 PM
 */
class CTask extends CTaskDatabase
{
    private $taskID;
    private $taskTitle;
    private $taskDueDate;
    private $taskTimeRequired;
    private $taskRepeatingWeekly;
    private $taskAlreadyComplete;

    private $taskCategory;
    private $usersID;

    public $newCategory;

    // status default 0
    // TODO: save timestamp of completed task and then graph most productive days and weeks | Average time to complete task

    public function __construct()
    {
        parent::__construct();
    }

    private function processPostData($data)
    {
        $this->taskTitle = mysqli_real_escape_string($this->mysqliConnectionLink, $data['TaskTitle']);
        $this->taskDueDate = strtotime($data['TaskDueDate']);
        $this->taskTimeRequired = mysqli_real_escape_string($this->mysqliConnectionLink, $data['TaskTimeRequired']);   //in hours
        $this->taskRepeatingWeekly = (isset($data['TaskRepeatingWeekly']) ? '1' : '0');
        $this->taskCategory = (isset($data['taskCategory']) ? intval($data['taskCategory']) : '0');
        $this->taskAlreadyComplete = (isset($data['AlreadyCompleteTask']) ? '1' : '0');
    }

    private function loadByID($taskID = false): void
    {
        if (!$taskID) {
            if (intval($this->taskID) > 0) {
                $taskID = $this->taskID;    // no task ID passed, so it's already loaded
            } else {
                return;
            }
        }

        $taskResult = mysqli_query($this->mysqliConnectionLink, "SELECT * FROM `TaskMasterTasks` WHERE `ID` = " . intval($taskID) . " LIMIT 1");
        $results = mysqli_fetch_array($taskResult);
        $this->taskTitle = $results['Title'];
        $this->taskDueDate = $results['DueDate'];
        $this->taskTimeRequired = $results['TimeRequired'];
        $this->taskRepeatingWeekly = $results['RepeatingWeekly'];
        $this->usersID = $results['UsersID'];
        $this->taskCategory = $results['CategoriesID'];
    }

    private function duplicateTask($taskID = false, $daysInFuture = 7)
    {
        if (!$taskID) {
            // already loaded
        } else {
            $this->loadByID( intval($taskID) );
        }

        // verify
        if (isset($this->taskTitle) && $this->taskTitle != '' && floatval($this->taskTimeRequired) > 0) {
            $this->taskTitle = mysqli_real_escape_string($this->mysqliConnectionLink, $this->taskTitle);
            $this->taskDueDate += (60 * 60 * 24 * $daysInFuture);   // update due date to NEXT WEEK or tomorrow
            $this->taskTimeRequired = floatval($this->taskTimeRequired);
            $this->taskRepeatingWeekly = intval($this->taskRepeatingWeekly);
            $this->taskCategory = intval($this->taskCategory);
            $this->usersID = intval($this->usersID);

            return $this->saveTask('', true); // and SAVE
        } else {
            return 2;
        }
    }

    /**
     * @param $userCategories
     * @param $categoriesID
     * @param $taskID
     * @param bool $inlineOnChange
     * @return string
     */
    private function buildCategoriesAreaWithID($userCategories, $categoriesID, $taskID, bool $inlineOnChange = false): string
    {
        if ($inlineOnChange) { // necessary because the datatable plugin conflicts with the jQuery onChange trigger
            $inlineOnChange = 'onChange="updateTaskCategory(); return false;"';
        } else {
            $inlineOnChange = '';
        }

        $output = '<select tabindex="20" data-tbi="' . $taskID . '" class="categoryDropdown" ' . $inlineOnChange . ' style="background: ' . ($categoriesID == 0 ? 'darkgrey' : $userCategories[$categoriesID][1]) . '; border-radius: .35rem; font-size: .7rem; color: white; padding: 1px 3px;">';
        if ($categoriesID == 0) $output .= '<option value="0">Uncategorized</option>';
        foreach ($userCategories as $userCategoryID => $categoryData) {
            $output .= '<option ' . ($userCategoryID == $categoriesID ? 'selected="selected"' : '') . ' value="' . $userCategoryID . '">' . $categoryData[0] . '</option>';
        }
        $output .= '</select>';

        return $output;
    }

    /**
     * @return float
     */
    public function getTodaysCompletedHours(): float
    {
        $taskResult = mysqli_query($this->mysqliConnectionLink, "SELECT SUM(`TimeRequired`) as TotalTimeCompleted FROM `TaskMasterTasks` WHERE `Status` = 1 AND `OnHold` = 0 AND `UsersID` = " . $this->usersID . " AND `TimeCompleted` >= " . strtotime('today') . " AND `DueDate` < " . strtotime('tomorrow') . " AND `TimeCompleted` != 0");
        $results = mysqli_fetch_array($taskResult);
        return floatval($results['TotalTimeCompleted']);
    }

    public function getQueuedTime($targetDate = 'today')
    {
        if (intval($this->usersID) == 0) {
            return 'Invalid User';
        }

        if ($targetDate == 'tomorrow') {   // if after 5pm, get tomorrow, otherwise today
            $query = "SELECT SUM(`TimeRequired`) as totalTimeRequired FROM `TaskMasterTasks` WHERE `Status` = 0 AND `OnHold` = 0 AND `UsersID` = " . $this->usersID . " AND `DueDate` >= " . strtotime('tomorrow') . " AND `DueDate` < " . (strtotime('tomorrow') + 60 * 60 * 24) . " AND `TimeCompleted` = 0";
        } else {  // first, get everything that is NOT done today and yesterday (we'll add completed afterward for an accurate "queued time" (2 hours/10 hours)
            $query = "SELECT SUM(`TimeRequired`) as totalTimeRequired FROM `TaskMasterTasks` WHERE `Status` = 0 AND `OnHold` = 0 AND `UsersID` = " . $this->usersID . " AND `DueDate` < " . strtotime('tomorrow') . " AND `TimeCompleted` = 0";
        }

        $results = mysqli_query($this->mysqliConnectionLink, $query) or die(mysqli_error($this->mysqliConnectionLink) . ' - ' . $query);
        $row = mysqli_fetch_array($results);
        return floatval($row['totalTimeRequired']);
    }

    /**
     * // Today at a glance
     * @param $userCategories
     * @param bool $JSONonly
     * @return false|string
     */
    public function getTodaysTask($userCategories, bool $JSONonly = false)
    {
        $taskResult = mysqli_query($this->mysqliConnectionLink, "SELECT * FROM `TaskMasterTasks` WHERE `OnHold` = 0 AND `UsersID` = " . $this->usersID . " AND `DueDate` >= " . strtotime('today') . " AND `DueDate` < " . strtotime('tomorrow') . " ORDER BY `Status` ASC, `CategoriesID` ASC, `TimeRequired` ASC");

        if ($JSONonly) {
            $output = array();
            while ($row = mysqli_fetch_array($taskResult)) {
                $output[] = array('Title' => $row['Title'], 'TimeCompleted' => $row['TimeCompleted'], 'TimeRequired' => $row['TimeRequired']);
                break;
            }

            $output = json_encode($output);
        } else {
            $output = "<tbody>";

            while ($row = mysqli_fetch_array($taskResult)) {

                $TimeCompleted = ($row['TimeCompleted'] == 0 ? 'In progress' : 'Due: ' . date('l jS g:ia', $row['DueDate']) . '. Completed at ' . date('l jS g:ia', $row['TimeCompleted']));

                $categoryTitle = 'Uncategorized';
                $categoryColor = '#efefef';
                if ($row['CategoriesID'] != 0) {
                    $categoryTitle = $userCategories[$row['CategoriesID']][0];
                    $categoryColor = $userCategories[$row['CategoriesID']][1];
                }

                $output .= '<tr style="font-weight: 400;">
                            <td>' . $row['Title'] . '</td>
                            <td class="' . ($TimeCompleted == 'In progress' ? '' : 'alert-success') . '"><span style="font-size: 0;">' . $row['TimeCompleted'] . '</span>' . $TimeCompleted . '</td>
                            <td>' . $row['TimeRequired'] . '</td>
                            <td style="color: ' . $categoryColor . '">' . $categoryTitle . '</td>
                       </tr>';
            }

            $output .= '</tbody>';
        }

        return $output;
    }

    public function getAll($userCategories, $onHold = false): string
    {

        if ($onHold) {
            $taskResult = mysqli_query($this->mysqliConnectionLink, "SELECT * FROM `TaskMasterTasks` WHERE `UsersID` = " . $this->usersID . " AND `OnHold` = 1 ORDER BY `DueDate` ASC");
        } else {
            $taskResult = mysqli_query($this->mysqliConnectionLink, "SELECT * FROM `TaskMasterTasks` WHERE `UsersID` = " . $this->usersID . " ORDER BY `DueDate` ASC");
        }

        $output = "<tbody>";

        while ($row = mysqli_fetch_array($taskResult)) {

            $categoriesOptions = $this->buildCategoriesAreaWithID($userCategories, $row['CategoriesID'], $row['ID']);

            if ($onHold) {
                $output .= '<tr>
                            <td>' . $row['Title'] . '</td>
                            <td>' . date('F, jS', $row['DueDate']) . '</td>
                            <td>' . $row['TimeRequired'] . '</td>
                            <td>' . $categoriesOptions . '</td>
                            <td> <a href="#" class="take-off-hold" data-tbi="' . $row['ID'] . '">Take Off-Hold</a></td>
                       </tr>';
            } else {
                $TimeCompleted = ($row['TimeCompleted'] == 0 ? 'In progress' : date('g:ia l, F jS', $row['TimeCompleted']));

                $output .= '<tr>
                            <td>' . $row['Title'] . '</td>
                            <td><span style="font-size: 0;">' . $row['TimeCompleted'] . '</span>' . $TimeCompleted . '</td>
                            <td>' . $row['TimeRequired'] . '</td>
                            <td>' . $categoriesOptions . '</td>
                       </tr>';
            }
        }

        $output .= '</tbody>';

        return $output;
    }

    /**
     * @param $POST
     * @param $duplication
     * @param $userCategories
     * @return int|string
     */
    public function saveTask($POST, $duplication = false, $userCategories = array())
    {
        if (!$duplication) {
            $this->processPostData($POST);
        }

        // If taskCategory is 0, let's take our best guess on the category based on task title
        if ($this->taskCategory == 0 && !empty($userCategories)) {

            // For each of the categories, if the category name is in the task title, it "should" be the category
            foreach ($userCategories as $ID => $NameAndColor) {
                if (strpos($this->taskTitle, $NameAndColor[0]) !== false) {
                    // found category in task title!
                    $this->taskCategory = $ID;
                }
            }

            // if still zero, let's try to query for it based on task title
            if ($this->taskCategory == 0) {
                $categoryQuery = "SELECT `CategoriesID` FROM `TaskMasterTasks` WHERE `Title` = '" . $this->taskTitle . "' LIMIT 1";
                $categoryResource = mysqli_query($this->mysqliConnectionLink, $categoryQuery);

                if (mysqli_num_rows($categoryResource) > 0) {
                    $results = mysqli_fetch_array($categoryResource);
                    $this->taskCategory = $results['CategoriesID'];
                }
            }
        }

        if ($this->taskAlreadyComplete) {
            $status = 1;
            $timeCompleted = time();

            $query = "INSERT INTO `TaskMasterTasks` (`TimeCreated`, `Title`, `DueDate`, `TimeRequired`, `RepeatingWeekly`, `UsersID`, `CategoriesID`, `Status`, `TimeCompleted`) 
                                                VALUES (" . time() . ", '" . $this->taskTitle . "', '" . $this->taskDueDate . "', '" . $this->taskTimeRequired . "', '" . $this->taskRepeatingWeekly . "', " . $this->usersID . ", " . $this->taskCategory . ", " . $status . ", " . $timeCompleted . ")";
        } else {
            $query = "INSERT INTO `TaskMasterTasks` (`TimeCreated`, `Title`, `DueDate`, `TimeRequired`, `RepeatingWeekly`, `UsersID`, `CategoriesID`) 
                                                VALUES (" . time() . ", '" . $this->taskTitle . "', '" . $this->taskDueDate . "', '" . $this->taskTimeRequired . "', '" . $this->taskRepeatingWeekly . "', " . $this->usersID . ", " . $this->taskCategory . ")";
        }

        if (mysqli_query($this->mysqliConnectionLink, $query)) {
            if ($duplication)
                return 11;  // Dup save success
            else
                return 1;   // normal save success
        } else {
            return 'Error Saving Data. Please contact AgencyAMG Support for help.';
        }
    }

    public function listIncompleteTasks($asBlocks = true, $userCategories): string
    {
        $taskResult = mysqli_query($this->mysqliConnectionLink, "SELECT * FROM `TaskMasterTasks` WHERE `UsersID` = " . $this->usersID . " AND `Status` = 0 AND `OnHold` = 0 ORDER BY `TimeStarted` DESC, `DueDate` ASC, `CategoriesID` ASC, `TimeRequired` ASC");
        $output = "";

        if ($asBlocks) {

            $oldDueDate = '';

            while ($row = mysqli_fetch_array($taskResult)) {
                $newDueDate = date('l F jS', $row['DueDate']);

//                if($oldDueDate != $newDueDate && $oldDueDate != '') {
//                    $output .= '<div class="card mb-4 py-3 border-bottom-warning">
//                                    <div class="card-body text-center">
//                                      - end of day -
//                                    </div>
//                                  </div>';
//                }

                if ($oldDueDate != $newDueDate) {
                    $output .= '<div class="card mb-4 py-3 border-bottom-warning">
                                    <div class="card-body text-center">
                                      ' . date('l F jS', $row['DueDate']) . '
                                    </div>
                                  </div>';
                }

                $cardHeight = $row['TimeRequired'] * 75;
                if ($cardHeight < 75)
                    $cardHeight = 75;
                else if ($cardHeight > 150)
                    $cardHeight = 150;

                $clocks = "";

                if (floatval($row['TimeRequired']) < 1.0) {
                    $clocks = '<i style="font-size: .65rem" class="fas fa-stopwatch"></i>';
                } else {
                    $clockTime = intval($row['TimeRequired']);

                    for ($i = 0; $i < $clockTime; $i++) {
                        $clocks .= '<i class="fas fa-stopwatch"></i>';
                    }

                    if (strpos($row['TimeRequired'], '.5') !== false) {
                        $clocks .= '<i style="font-size: .65rem" class="fas fa-stopwatch"></i>';
                    }
                }

                $secondsSpentOnTask = 0;
                if ($row['TimeStarted'] > 0) {
                    $secondsSpentOnTask = time() - $row['TimeStarted'];
                }

                $overTimeLimit = false;
                if ($secondsSpentOnTask > ($row['TimeRequired'] * 60 * 60)) {
                    $overTimeLimit = true;
                }

                $output .= '
                <div class="taskBlock card shadow mb-4 ' . ($row['DueDate'] < strtotime('today') ? 'border-bottom-danger' : '') . '" id="taskCard_' . $row['ID'] . '" data-tbi="' . $row['ID'] . '">
                <!-- Card Header  -->
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                  <h6 class="m-0 font-weight-bold text-primary">' .
                    $clocks . ' | ' .
                    $row['Title'] .
                    ($row['RepeatingWeekly'] == 1 ? ' <i class="fas fa-redo"></i>' : ' <a class="puntTask" href="#" data-tbi="' . $row['ID'] . '" title="Punt Until Tomorrow"><i ' . ($row['PuntCount'] >= 2 ? 'style="color: #f6c23e;"' : '') . ' class="fas fa-football-ball"></i></a> &nbsp;') .
                    ($overTimeLimit ? '<span class="btn-warning" style="padding: 1px 8px; calc(.35rem - 1px);">' : '') .
                    ($row['TimeStarted'] > 0 ? '| Started at: ' . date('H:ia', $row['TimeStarted']) : '') .
                    ($overTimeLimit ? '</span>' : '') .
                    '</h6>
                  <div class="timer" style="display: none;">' . date('g:i a') . '</div>
                  <div class="dropdown no-arrow">
                    <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                      <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                    </a>
                    
                    <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="dropdownMenuLink" x-placement="bottom-end" style="position: absolute; will-change: transform; top: 0px; left: 0px; transform: translate3d(17px, 19px, 0px);">
                      <div class="dropdown-header">Task Options:</div>
                      <a class="dropdown-item startTimer" data-tbi="' . $row['ID'] . '" href="#"><i class="fas fa-stopwatch"></i> Start Timer </a>
                      <a class="dropdown-item markCompletedFollowupTomorrow" data-tbi="' . $row['ID'] . '" href="#"><i class="fas fa-forward"></i> Complete + Follow-up</a>
                      <a class="dropdown-item completedYesterday" data-tbi="' . $row['ID'] . '" href="#"><i class="fas fa-reply"></i> Completed Yesterday</a>
                      <a class="dropdown-item puntTask" href="#" data-tbi="' . $row['ID'] . '" title="Punt Until Tomorrow"><i ' . ($row['PuntCount'] >= 2 ? 'style="color: #f6c23e;"' : '') . ' class="fas fa-football-ball"></i> Punt Until Tomorrow</a>
                      <a class="dropdown-item onHold" data-tbi="' . $row['ID'] . '" href="#"><i class="fas fa-hand-paper"></i> Place On-Hold</a>
                      
                      <div class="dropdown-divider"></div>
                      <a class="dropdown-item deleteTask" data-tbi="' . $row['ID'] . '" href="#"><i class="fas fa-trash-alt"></i> Delete </a>
                    </div>
                  </div>
                  
                </div>
                <!-- Card Body -->
                <div class="card-body" style="height: ' . $cardHeight . 'px;"> 
                Due: ' . (date('l, F jS', $row['DueDate']) == date('l, F jS', time()) ? '<strong>Today,</strong> ' : '') . date('l, F jS', $row['DueDate']) . ' | <span id="TimeRequired_' . $row['ID'] . '">' . $row['TimeRequired'] . '</span> hrs <a href="#" data-tbi="' . $row['ID'] . '" class="addTaskTimeRequired" title="add .5 hours"><i class="fas fa-plus-circle"></i></a> ' . ($row['PuntCount'] > 7 ? '| ' . $row['PuntCount'] . ' punts' : '') . ' 
                <br />' . $this->buildCategoriesAreaWithID($userCategories, $row['CategoriesID'], $row['ID']) . '
                </div>
              </div>';

                $oldDueDate = date('l F jS', $row['DueDate']);
            }
        } else {
            //  while($row = mysqli_fetch_array($taskResult)) {
            //  TODO: other formats
            //  }
        }

        return $output;
    }

    public function setID($id)
    {
        $this->taskID = intval($id);
    }

    public function setUsersID($userID)
    {
        $this->usersID = $userID;
    }

    public function updateTaskCompleted()
    {
        if (mysqli_query($this->mysqliConnectionLink, "UPDATE `TaskMasterTasks` SET `Status` = 1, `TimeCompleted` = " . time() . " WHERE `ID` = " . $this->taskID . " LIMIT 1")) {

            $this->loadByID();

            if ($this->taskRepeatingWeekly == 1) {
                return $this->duplicateTask();
            }
            return 1;
        }

        return 0;
    }

    public function markCompletedFollowupTomorrow()
    {
        if ($this->updateTaskCompleted() == 1) {
            return $this->duplicateTask(false, 1);
        }
    }

    public function updateTaskCompletedYesterday()
    {
        if (mysqli_query($this->mysqliConnectionLink, "UPDATE `TaskMasterTasks` SET `Status` = 1, `TimeCompleted` = " . (time() - (60 * 60 * 24)) . " WHERE `ID` = " . $this->taskID . " LIMIT 1")) {

            $this->loadByID();

            if ($this->taskRepeatingWeekly == 1) {
                return $this->duplicateTask();
            }
            return 1;
        }

        return 0;
    }

    public function onHold()
    {
        if (intval($this->taskID) > 0) {
            if (mysqli_query($this->mysqliConnectionLink, "UPDATE `TaskMasterTasks` SET `OnHold` = 1 WHERE `ID` = " . $this->taskID . " LIMIT 1")) {
                return 1;
            }
        }

        return 0;
    }

    public function taskOffHold()
    {
        if (intval($this->taskID) > 0) {
            if (mysqli_query($this->mysqliConnectionLink, "UPDATE `TaskMasterTasks` SET `OnHold` = 0 WHERE `ID` = " . $this->taskID . " LIMIT 1")) {
                return 1;
            }
        }

        return 0;
    }

    /**
     * @return int
     */
    public function updateTaskDelete(): int
    {
        if (intval($this->taskID) > 0) {
            if (mysqli_query($this->mysqliConnectionLink, "DELETE FROM `TaskMasterTasks` WHERE `ID` = " . intval($this->taskID) . " LIMIT 1")) {
                return 1;
            }
        }
        return 0;
    }

    public function addTaskTimeRequired()
    {
        if (intval($this->taskID) > 0) {
            if (mysqli_query($this->mysqliConnectionLink, "UPDATE `TaskMasterTasks` SET `TimeRequired` = `TimeRequired` + 0.5 WHERE `ID` = " . intval($this->taskID) . " LIMIT 1")) {
                return 1;
            }
        }

        return 0;
    }

    /**
     * Move task until tomorrow
     * @return int
     */
    public function updateTaskPunt(): int // TODO: and clientID =
    {
        if (intval($this->taskID) > 0) {
            if (mysqli_query($this->mysqliConnectionLink, "UPDATE `TaskMasterTasks` SET `DueDate` = `DueDate` + (60 * 60 * 24), `PuntCount` = `PuntCount`+1 WHERE `ID` = " . intval($this->taskID) . " LIMIT 1")) {
                return 1;
            }
        }
        return 0;
    }

    public function updateTaskCategory()
    {
        if (intval($this->taskID) > 0 && intval($this->newCategory) > 0) {
            if (mysqli_query($this->mysqliConnectionLink, "UPDATE `TaskMasterTasks` SET `CategoriesID` = " . intval($this->newCategory) . " WHERE `ID` = " . intval($this->taskID) . " LIMIT 1")) {
                return 1;
            }
        }
        return 0;
    }

    /**
     * // How much work do I get done, on average each day, the last (7) days
     * @param $dateRange
     * @return string
     */
    public function getAverageCompletedHours($dateRange = 7): string
    {
        $query = "SELECT SUM(`TimeRequired`) as TotalTimeRequired FROM `TaskMasterTasks` WHERE `UsersID` = " . $this->usersID . " AND `TimeCompleted` > " . strtotime('-' . $dateRange . ' days');
        $results = mysqli_fetch_array(mysqli_query($this->mysqliConnectionLink, $query));
        return number_format($results['TotalTimeRequired'] / $dateRange, 1);
    }

    /**
     * @param $userCategories
     * @param $range
     * @return string
     */
    public function getProductivityByCategory($userCategories, $range = 'weekly'): string
    {
        if ($range == 'weekly') $range = strtotime('-6 days');
        else if ($range == 'monthly') $range = strtotime('-30 days');
        else $range = strtotime('-6 days');

        $query = "SELECT SUM(TimeRequired) as totalTimeRequired, CategoriesID FROM `TaskMasterTasks` WHERE `TimeCompleted` > " . $range . " AND `Status` = 1 GROUP BY `CategoriesID`";
        $results = mysqli_query($this->mysqliConnectionLink, $query);
        $output = "";
        $sumTime = 0;

        while ($row = mysqli_fetch_array($results)) {   // TODO: if 0 totalTime, skip it?
            $output .= ($row['CategoriesID'] == 0 ? 'Uncategorized' : $userCategories[$row['CategoriesID']][0]) . ' : ' . $row['totalTimeRequired'] . ' hours <br />';
            $sumTime += $row['totalTimeRequired'];
        }

        return $output . '<br /><strong>Total: ' . $sumTime . '</strong>';
    }

    /**
     * @param $dayRange
     * @return array
     */
    public function getWeeklyProductivity($dayRange = 7): array
    {
        $query = "SELECT `TimeRequired`, `TimeCompleted` FROM `TaskMasterTasks` WHERE `TimeCompleted` > " . (strtotime('-' . $dayRange . ' days'));
        $resource = mysqli_query($this->mysqliConnectionLink, $query);

        $output = array();
//        $dailyTime = array(); // unused at the moment
        $weekDays = array();

        while ($row = mysqli_fetch_assoc($resource)) {

            if (array_key_exists(strtotime(date('F jS, Y', $row['TimeCompleted'])), $weekDays)) {

                $weekDays[strtotime(date('F jS, Y', $row['TimeCompleted']))] += $row['TimeRequired'];
            } else {
                $weekDays[strtotime(date('F jS, Y', $row['TimeCompleted']))] = $row['TimeRequired'];
            }
        }

        ksort($weekDays);

        foreach ($weekDays as $weekDay => $hours) {
            $output[0][] = date('D jS', $weekDay);
            $output[1][] = $hours;
        }

        return $output;
    }

    public function updateTaskStartTimer(): int // TODO: and clientID =
    {
        if (intval($this->taskID) > 0) {

            if (mysqli_query($this->mysqliConnectionLink, "UPDATE `TaskMasterTasks` SET `TimeStarted` = " . time() . " WHERE `ID` = " . intval($this->taskID) . " LIMIT 1")) {
                return 1;
            }
        }

        return 0;
    }

    /**
     * @return array
     */
    public function cronGetEndOfDayIncomplete(): array
    {
        // 12/15/20 update: skip OnHold Tasks

        $query = "SELECT `TaskMasterUsers`.`Email`, 
                            `TaskMasterTasks`.`Title`, 
                            `TaskMasterTasks`.`ID`, 
                            `TaskMasterTasks`.`DueDate`, 
                            `TaskMasterTasks`.`TimeRequired` 
                  FROM `TaskMasterTasks`, `TaskMasterUsers` 
                  WHERE `UsersID` = `TaskMasterUsers`.`ID` 
                    AND `Status` = 0 
                    AND `OnHold` = 0 
                    AND DueDate < " . strtotime('tomorrow') . " 
                  ORDER BY `Title`";
        $resource = mysqli_query($this->mysqliConnectionLink, $query);

        $userEmails = array();
        $emailData = array();

        while ($row = mysqli_fetch_array($resource)) {

            if (!in_array($row['Email'], $userEmails)) {
                $userEmails[] = $row['Email'];
            }

            $emailData[$row['Email']][] = array($row['Title'], date('F jS', $row['DueDate']), $row['TimeRequired'], $row['ID']);
        }

        return array($userEmails, $emailData);
    }

    public function cronGetTomorrowsTodos()
    {
        $query = "SELECT `TaskMasterUsers`.`Email`, `TaskMasterTasks`.`Title`, `TaskMasterTasks`.`DueDate`, `TaskMasterTasks`.`TimeRequired` FROM `TaskMasterTasks`, `TaskMasterUsers` WHERE `UsersID` = `TaskMasterUsers`.`ID` AND `Status` = 0 AND DueDate < " . strtotime('+2 days') . " ORDER BY `Title`";
        $resource = mysqli_query($this->mysqliConnectionLink, $query);

        $userEmails = array();
        $emailData = array();

        while ($row = mysqli_fetch_array($resource)) {

            if (!in_array($row['Email'], $userEmails)) {
                $userEmails[] = $row['Email'];
            }

            $emailData[$row['Email']][] = array($row['Title'], date('F jS', $row['DueDate']), $row['TimeRequired']);
        }

        return array($userEmails, $emailData);
    }

    public function getMostRecentlyPopularTasksAsSuggestionButtons()
    {
        $output = '<div class="col-lg-12">';
        $query = "SELECT `Title`, COUNT(`Title`) AS `value_occurrence` 
                    FROM  `TaskMasterTasks`
                    WHERE `TimeCompleted` > " . strtotime('8 days ago') . "
                    GROUP BY `Title`
                    ORDER BY `value_occurrence` DESC
                    LIMIT 3";

        $resource = mysqli_query($this->mysqliConnectionLink, $query);
        while ($row = mysqli_fetch_array($resource)) {
            if ($row['value_occurrence'] > 1) {
                $output .= '<input tabindex="10" type="submit" value="' . $row['Title'] . '" class="btn btn-outline-info btn-user btn-block TaskSuggestion" />';
            }
        }

        $output .= '</div>';
        return $output;
    }

    /**
     * @param $post
     * @return int
     */
    public function saveOverviewData($post): int
    {
        $title = $post['projectTitle'];
        $date = strtotime($post['projectDate']);
        $image = $post['projectImage'];

        // TODO: check if exist and delete
        $query = "INSERT INTO `TaskMasterOverviewData` (`ProjectTitle`, `ProjectDate`, `ProjectImage`) VALUES('" . $title . "', " . $date . ", '" . $image . "')";
        if (mysqli_query($this->mysqliConnectionLink, $query))
            return 1;
        else
            return 0;
    }

    public function getOverviewerProjects($targetDate)
    {
        // TODO: clientID/user tie in
        // TODO: date restriction for the month

        $query = "SELECT * FROM `TaskMasterOverviewData` WHERE ProjectDate >= " . $targetDate . " ORDER BY `ProjectTitle` ASC, `ID` DESC";
        $resource = mysqli_query($this->mysqliConnectionLink, $query);

        $outputArray = array();
        $oldProjectTitle = "";
        $projectData = array();

        $fileLocationPrefix = $this->fileLocationPrefix;

        while ($row = mysqli_fetch_array($resource)) {

            $newProjectTitle = $row['ProjectTitle'];

            // we're either building up part two or inserting the whole array
            if ($newProjectTitle != $oldProjectTitle && $oldProjectTitle != "") {
                $outputArray[] = array($oldProjectTitle, $projectData); // we found a new project title, so insert old one

                $projectData = array(); // clear project data
                $projectData[] = array(date('n/j/Y', $row['ProjectDate']), $fileLocationPrefix . $row['ProjectImage']);  // Add data for new project title
            } else {
                $projectData[] = array(date('n/j/Y', $row['ProjectDate']), $fileLocationPrefix . $row['ProjectImage']);
            }

            $oldProjectTitle = $newProjectTitle;
        }

        // for the very last entry or if there is only one entry
        $outputArray[] = array($oldProjectTitle, $projectData); // we found a new project title, so insert old one

        return $outputArray;
    }
}