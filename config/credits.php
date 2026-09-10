<?php

return [

    /*
    | Credits charged for one room availability inquiry.
    */
    'inquiry_cost' => (int) env('CREDITS_INQUIRY_COST', 5),

    /*
    | Timezone in which "start of the month" is evaluated for the recharge job.
    */
    'timezone' => env('CREDITS_TIMEZONE', 'Asia/Jakarta'),

];
