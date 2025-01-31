<?php

/*
 * This file is part of SeAT
 *
 * Copyright (C) 2015, 2016, 2017, 2018, 2019  Leon Jacobs
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

namespace Seat\Notifications\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\SlackMessage;
use Seat\Services\Image\Eve;

/**
 * Class Killmail.
 * @package Seat\Notifications\Notifications
 */
class Killmail extends AbstractNotification
{
    const LOSS_COLOR = '#DD4B39';
    const KILL_COLOR = '00A65A';
    /**
     * @var
     */
    private $killmail;

    /**
     * Create a new notification instance.
     *
     * @param $killmail
     */
    public function __construct($killmail)
    {

        $this->killmail = $killmail;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed $notifiable
     *
     * @return array
     */
    public function via($notifiable)
    {

        return $notifiable->notificationChannels();
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed $notifiable
     *
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {

        return (new MailMessage)
            ->subject('Killmail Notification')
            ->line(
                'A new killmail has been recorded!'
            )
            ->line(
                'Lost a ' .
                $this->killmail->killmail_victim->ship_type->typeName . ' in ' .
                $this->killmail->killmail_victim->ship_type->itemName . ' (' .
                number_format($this->killmail->killmail_detail->solar_system->security, 2) . ')'
            )
            ->action(
                'Check it out on zKillboard',
                'https://zkillboard.com/kill/' . $this->killmail->killmail_id . '/'
            );
    }

    /**
     * Get the Slack representation of the notification.
     *
     * @param $notifiable
     *
     * @return SlackMessage
     */
    public function toSlack($notifiable)
    {

        $icon_url = sprintf('https:%s',
            (new Eve('type', $this->killmail->killmail_victim->ship_type_id, 64, [], false))->url(64));

        $message = (new SlackMessage)
            ->content('Reactor Breach Detected!')
            ->from('Defense Mainframe', $icon_url)
            ->attachment(function ($attachment) use ($icon_url) {

                $attachment
                    ->timestamp(carbon($this->killmail->killmail_time))
                    ->fields([
                        'Ship Type' => $this->killmail->killmail_victim->ship_type->typeName,
                        'zKB Link'  => 'https://zkillboard.com/kill/' . $this->killmail->killmail_id,
                        'Value'     => $this->getValue($this->killmail_detail->killmail_id),
                        'Involved Pilots' => $this->getNumberOfAttackers(),
                    ])
                    ->field(function ($field) {

                        $field->title('System')
                            ->content($this->zKillBoardToSlackLink(
                                'system',
                                $this->killmail->killmail_detail->solar_system_id,
                                $this->killmail->killmail_detail->solar_system->itemName . ' (' .
                                number_format($this->killmail->security, 2) . ')'));
                    })
                    ->thumb($icon_url)
                    ->fallback('Kill details')
                    ->footer('zKillboard')
                    ->footerIcon('https://zkillboard.com/img/wreck.png')
                    ->color($this->is_loss($notifiable) ? self::LOSS_COLOR : self::KILL_COLOR);
            });

        ($this->killmail->corporation_id === $this->killmail->killmail_victim->corporation_id) ?
            $message->error() : $message->success();

        return $message;
    }

     private function getNotificationString(): string
    {
        
        return sprintf('%s just killed %s %s',
            $this->getAttacker(),
            $this->getVictim(),
            $this->getNumberofAttackers() == 1 ? 'solo.' : ''
        );
    }
    
    private function getAttacker(): string
    {
        $killmail_attacker = $this->killmail_detail
            ->attackers
            ->where('final_blow', 1)
            ->first();
        return $this->getSlackKMStringPartial(
            $killmail_attacker->character_id,
            $killmail_attacker->corporation_id,
            $killmail_attacker->ship_type_id,
            $killmail_attacker->alliance_id
        );
    }
     
    private function getVictim(): string
    {
        $killmail_victim = $this->killmail_detail->victims;
        return $this->getSlackKMStringPartial(
            $killmail_victim->character_id,
            $killmail_victim->corporation_id,
            $killmail_victim->ship_type_id,
            $killmail_victim->alliance_id
        );
    }
     
        private function getSlackKMStringPartial($character_id, $corporation_id, $ship_type_id, $alliance_id): string
    {
        $character = is_null($character_id) ? null : $this->resolveID($character_id);
        $corporation = is_null($corporation_id) ? null : $this->resolveID($corporation_id);
        $alliance = is_null($alliance_id) ? null : strtoupper('<' . $this->resolveID($alliance_id, true) . '>');
        $ship_type = optional(InvType::find($ship_type_id))->typeName;
        if (is_null($character_id))
            return sprintf('*%s* [%s] %s)',
                $ship_type,
                $corporation,
                $alliance
            );
        if (! is_null($character_id))
            return sprintf('*%s* [%s] %s flying a *%s*',
                $character,
                $corporation,
                $alliance,
                $ship_type
            );
        return '';
    }
                        
    /**
     * Get the array representation of the notification.
     *
     * @param  mixed $notifiable
     *
     * @return array
     */
    public function toArray($notifiable)
    {

        return [
            'characterName'   => $this->killmail->characterName,
            'corporationName' => $this->killmail->corporationName,
            'typeName'        => $this->killmail->typeName,
            'itemName'        => $this->killmail->itemName,
            'security'        => $this->killmail->security,
        ];
    }
}
