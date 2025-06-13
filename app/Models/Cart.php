<?php

class Cart
{
    private $items = [];
    
    public function addItem(Book $book)
    {
        $this->items[] = $book;
    }
    
    public function getItems()
    {
        return $this->items;
    }
}
