<?php

namespace App\Notifications\Peoplecount;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\VonageMessage;
use Illuminate\Notifications\Notification;

class AreaOccupancyAlert extends Notification implements ShouldQueue
{
    use Queueable;

    /** @var list<string> */
    public array $channels;

    /**
     * @param  list<string>  $channels
     */
    public function __construct(
        public string $eventName,
        public string $areaName,
        public int $currentOccupancy,
        public int $configuredThreshold,
        array $channels = ['mail', 'vonage'],
    ) {
        $this->channels = $channels;
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(sprintf('Occupancy alert: %s @ %s', $this->areaName, $this->eventName))
            ->greeting('Threshold Exceeded')
            ->line('Event: '.$this->eventName)
            ->line('Area: '.$this->areaName)
            ->line('Current occupancy: '.$this->currentOccupancy)
            ->line('Configured threshold: '.$this->configuredThreshold);
    }

    public function toVonage(object $notifiable): VonageMessage
    {
        $text = sprintf('Occupancy alert - %s / %s: ', $this->eventName, $this->areaName)
            .sprintf('%d (threshold %d).', $this->currentOccupancy, $this->configuredThreshold);

        return (new VonageMessage)->content($text);
    }
}
