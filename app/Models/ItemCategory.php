<?php

namespace App\Models;

// Category master of the ADMIN tab (D21): item categories with an item no prefix (D20). Not the CRM service categories.
class ItemCategory extends Master
{
    public function label(): string
    {
        return $this->name;
    }
}
