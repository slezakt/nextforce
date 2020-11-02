<?php
class NDummyBar extends NObject
{
    public function addPanel(IBarPanel $panel, $id = NULL)
    {
        return $this;
    }

    public function render()
    {
    }
}