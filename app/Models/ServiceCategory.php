<?php

namespace App\Models;

// Category master (CRM changes): the Enquiry's Service type, e.g. Plant Rental or Terrace Garden.
class ServiceCategory extends SimpleMaster
{
    protected $table = 'service_categories';
}
