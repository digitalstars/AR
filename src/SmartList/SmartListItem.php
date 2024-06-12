<?php


namespace DigitalStars\InterfaceDB\SmartList;


interface SmartListItem {
    public function getId(): mixed;
    public function raw(): mixed;
    public function getField(string $name): SmartListItemValue|SmartListItem;
    public function setField($name, $value): SmartListItem|null;
}
