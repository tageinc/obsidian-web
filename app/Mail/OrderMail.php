<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */

    public $name;
    public $invoice;
    public $total;
    public $products;
    public $date;

    public function __construct($data)
    {
        $this->name = $data['name'];
        $this->invoice = $data['invoice'];
        $this->total = $data['total'];
        $this->products = $data['products'];

        $dtz = new \DateTimeZone("America/Los_Angeles");
        $dt = new \DateTime(date($data['date']), $dtz);
        $this->date = date('Y-m-d H:i:s', strtotime( $dt->format('Y-m-d H:i:s')." GMT+7"));
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->markdown('emails.order');
    }
}
