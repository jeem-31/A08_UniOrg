<?php
namespace App\Notifications;
use App\Models\Election;
use App\Traits\HasOrganizationTheme;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
class ElectionAnnouncedNotification extends Notification implements ShouldQueue
{ use Queueable, HasOrganizationTheme; protected $election;
 public function __construct(Election $election){$this->election=$election;if(config('queue.default') !== 'sync'){$this->onConnection('redis');$this->onQueue('emails');}}
 public function via(object $notifiable):array{return ['mail','database'];}
 public function toMail(object $notifiable):MailMessage{$t=$this->getOrganizationTheme($notifiable,$this->election->organization);return (new MailMessage)->subject('New election: '.$this->election->title)->view(['html'=>'emails.election','text'=>'emails.election-text'],['organizationName'=>$t['organization_name'],'organizationLogo'=>$t['logo_url'],'primaryColor'=>$t['primary_color'],'recipientName'=>$notifiable->first_name,'title'=>'A new election is coming up','preheader'=>'Review the election details and prepare to vote.','intro'=>'Your organization has announced a new election. Review the schedule and details below so you know when and where to participate.','highlight'=>$this->election->title,'details'=>['Voting opens'=>$this->election->start_date->format('F j, Y, g:i A'),'Voting closes'=>$this->election->end_date->format('F j, Y, g:i A')],'body'=>$this->election->description,'actionLabel'=>'View election details','actionUrl'=>url('/elections')]);}
 public function toArray(object $notifiable):array{return ['type'=>'election_announced','election_id'=>$this->election->id,'organization_id'=>$this->election->organization_id,'title'=>'New Election Announced','message'=>$this->election->title,'icon'=>'ph-megaphone','color'=>'purple','url'=>url('/elections'),'start_date'=>$this->election->start_date->format('M j, Y g:i A'),'end_date'=>$this->election->end_date->format('M j, Y g:i A')];}
}
