<?php

/**
 * Created by PhpStorm.
 * User: adamgumm
 * Date: 7/2/20
 * Time: 6:23 PM
 */
class CTips
{
    private $tips;

    public function __construct()
    {
        $this->tips = array();

        $this->tips[] = "Clicking the football icon will move a task back 1 day";
        $this->tips[] = "You can re-categorize completed tasks in the <a href='./metrics.php'>Metrics Module</a>.";
        $this->tips[] = "You can create new categories in the <a href='./mCategories.php'>Categories Module</a>.";
        $this->tips[] = "You can mark a task as 'Already Complete' when you create it so you don't have to check it off later.";
        $this->tips[] = "If you type the Category Name in your Task Title, TaskMaster ai will automatically set the Category for you.";
        $this->tips[] = "If your new task title has the same name as an old task, the new task Category will automatically be set based on the old task.";

        // add more tips
    }

    public function getTip() {
        return $this->tips[array_rand($this->tips)];
    }
}