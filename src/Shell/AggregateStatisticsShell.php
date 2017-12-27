<?php
namespace App\Shell;

use Cake\Log\Log;
use Cake\ORM\TableRegistry;

class AggregateStatisticsShell extends \Cake\Console\Shell
{
    public function initialize()
    {
        parent::initialize();

        $this->Options = TableRegistry::get('Options');
        $this->Campaigns = TableRegistry::get('Campaigns');
        $this->Keywords = TableRegistry::get('Keywords');


        $this->AdGroups = TableRegistry::get('AdGroups');
        $this->AdGroupStatisticsDaily = TableRegistry::get('AdGroupStatisticsDaily');

		$this->SiteCalls = TableRegistry::get('SiteCalls');
		$this->SiteEmails = TableRegistry::get('SiteEmails');
    }

    public function today()
    {
		$this->forDate(date('Y-m-d'));
    }

	private function forDate($date)
	{
		$from = $date . ' 00:00:00';
		$to = $date . ' 23:59:59';

		$adGroups = $this->AdGroups->find('all')->all();
		foreach($adGroups as $adGroup) {

			$record = $this->AdGroupStatisticsDaily->find('all')
				->where([
					'ad_group_id' => $adGroup->id,
					'date' => $date,
				])
				->first();

			if(empty($record)) {
				$record = $this->AdGroupStatisticsDaily->newEntity();
				$record->ad_group_id = $adGroup->id;
				$record->date = $date;
			}

			$campaign = $this->Campaigns->get($adGroup->campaign_id);
			$keywordsList = $this->Keywords->find('list', ['keyField' => 'id', 'valueField' => 'rel_id'])->where(['campaign_id' => $campaign->id,])->toArray();

			$conditions = [
				'utm_campaign LIKE' => '%' . $campaign->rel_id . '%',
				'utm_term IN' => array_values($keywordsList),
				'time >=' => $from,
				'time <=' => $to,
			];

			$record->calls = $this->SiteCalls->findCountBy($conditions);
			$record->emails = $this->SiteCalls->findCountBy($conditions);

			$this->AdGroupStatisticsDaily->save($record);
		}
	}

}