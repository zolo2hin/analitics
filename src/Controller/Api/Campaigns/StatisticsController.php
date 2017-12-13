<?php
namespace App\Controller\Api\Campaigns;

use Cake\ORM\TableRegistry;

class StatisticsController extends \App\Controller\Api\ApiController
{

	public function initialize() {
		parent::initialize();
		$this->loadComponent('Validator');
		//$this->loadComponent('Cewi/Excel.Import');
	}

    public function summary()
    {
		if($this->Validator->required($this->request->query, ['from', 'to'])) {

			$fields = $this->request->query;

			$CampaignStatistics = TableRegistry::get('CampaignStatisticsDaily');
			$CampaignsTable = TableRegistry::get('Campaigns');
			$CallsTable = TableRegistry::get('SiteCalls');
			$EmailsTable = TableRegistry::get('SiteEmails');

			$campaign = $CampaignsTable->get($this->request->getParam('campaign_id'));

			$query = $CampaignStatistics->find('all', [
				'conditions' => [
					'campaign_id' => $campaign->id,
					'date >=' => $fields['from'],
					'date <=' => $fields['to']
				],
			]);
			$statistics = $query
    			->select([
					'total_clicks' => $query->func()->sum('clicks'),
					'total_cost' => $query->func()->sum('cost'),
					'total_views' => $query->func()->sum('views'),
				])
				->first();

			$calls = $CallsTable->findCountBy([
					'utm_campaign' => $campaign->rel_id,
					'time >=' => $fields['from'] . ' 00:00:00',
					'time <=' => $fields['to'] . ' 23:59:59',
				]);
			$emails = $EmailsTable->findCountBy([
					'utm_campaign LIKE' => '%' . $campaign->rel_id,
					'time >=' => $fields['from'] . ' 00:00:00',
					'time <=' => $fields['to'] . ' 23:59:59',
				]);

			if($statistics) {
				$this->sendData([
					'clicks' => $statistics->total_clicks,
					'views' => $statistics->total_views,
					'cost' => sprintf('%.2f', $statistics->total_cost),
					'calls' => $calls,
					'emails' => $emails,
				]);
			}
		}

		$this->sendError($this->Validator->getLastError());
    }

    /*public function summary()
    {
		if($this->Validator->required($this->request->query, ['from', 'to'])) {

			$query = $this->request->query;

			$interval = date_diff(new \DateTime($query['from']), new \DateTime($query['to']));

			if($interval->format('%d')) {
				$CampaignStatistics = TableRegistry::get('CampaignStatisticsDaily');
				$timeSelect = [
					'date >=' => $query['from'],
					'date <=' => $query['to']
				];
			} else {
				$CampaignStatistics = TableRegistry::get('CampaignStatisticsHourly');
				$timeSelect = [
					'time >=' => $query['from'] . ' 00:00:00',
					'time <=' => $query['to'] . ' 23:59:59'
				];
			}

			$query = $CampaignStatistics->find('all', [
				'conditions' => array_merge([
					'campaign_id' => $this->request->getParam('campaign_id'),
				], $timeSelect),
			]);

			$statistics = $query
    			->select([
					'total_clicks' => $query->func()->sum('clicks'),
					'total_cost' => $query->func()->sum('cost'),
					'total_views' => $query->func()->sum('views'),
				])
				->first();

			if($statistics) {
				$this->sendData([
					'clicks' => $statistics->total_clicks,
					'views' => $statistics->total_views,
					'cost' => sprintf('%.2f', $statistics->total_cost),
				]);
			}
		}

		$this->sendError($this->Validator->getLastError());
    }*/

	public function details() {

		if($this->Validator->required($this->request->query, ['from', 'to'])) {

			$fields = $this->request->query;

			$from = $fields['from'] . ' 00:00:00';
			$to = $fields['to'] . ' 23:59:59';

			$CampaignStatistics = TableRegistry::get('CampaignStatisticsHourly');
			$CampaignsTable = TableRegistry::get('Campaigns');
			$CallsTable = TableRegistry::get('SiteCalls');
			$EmailsTable = TableRegistry::get('SiteEmails');

			$campaign = $CampaignsTable->get($this->request->getParam('campaign_id'));

			$statistics = $CampaignStatistics->find('all', [
				'conditions' => [
					'campaign_id' => $campaign->id,
					'time >=' => $from,
					'time <=' => $to
				],
			])->all();

			$result = [];

			$calls = $CallsTable->find('all', [
					'conditions' => [
						'utm_campaign' => $campaign->rel_id,
						'time >=' => $from,
						'time <=' => $to,
					],
				])->all();
			$emails = $EmailsTable->find('all', [
					'conditions' => [
						'utm_campaign LIKE' => '%' . $campaign->rel_id,
						'time >=' => $from,
						'time <=' => $to,
					],
				])->all();

			$callsTime = [];
			$emailsTime = [];

			foreach($calls as $call) {
				$time = date('Y:m:d H:00:00', strtotime($call->time));
				if(!isset($callsTime[$time])) {
					$callsTime[$time] = 0;
				}
				$callsTime[$time]++;
			}
			foreach($emails as $email) {
				$time = date('Y:m:d H:00:00', strtotime($call->time));
				if(!isset($emailsTime[$time])) {
					$emailsTime[$time] = 0;
				}
				$emailsTime[$time]++;
			}

			foreach($statistics as $stat) {

				$cCount = 0;
				$eCount = 0;

				$time = date('Y:m:d H:00:00', strtotime($stat->time));

				// @NOTE: hack. statistic always presents
				if(isset($callsTime[$time])) {
					$cCount = $callsTime[$time];
				}
				if(isset($emailsTime[$time])) {
					$eCount = $emailsTime[$time];
				}

				$result[] = [
					'time' => $stat->time,
					'statistics' => [
						'clicks' => $stat->clicks,
						'views' => $stat->views,
						'cost' => sprintf('%.2f', $stat->cost),
						'calls' => $cCount,
						'emails' => $eCount,
					]
				];
			}

			$this->sendData($result);
		}

		$this->sendError($this->Validator->getLastError());
	}

    /**
     * View method
     *
     * @param string|null $id Statistic id.
     * @return \Cake\Http\Response|null
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $statistic = $this->Statistics->get($id, [
            'contain' => []
        ]);

        $this->set('statistic', $statistic);
        $this->set('_serialize', ['statistic']);
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $statistic = $this->Statistics->newEntity();
        if ($this->request->is('post')) {
            $statistic = $this->Statistics->patchEntity($statistic, $this->request->getData());
            if ($this->Statistics->save($statistic)) {
                $this->Flash->success(__('The statistic has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The statistic could not be saved. Please, try again.'));
        }
        $this->set(compact('statistic'));
        $this->set('_serialize', ['statistic']);
    }

    /**
     * Edit method
     *
     * @param string|null $id Statistic id.
     * @return \Cake\Http\Response|null Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Network\Exception\NotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $statistic = $this->Statistics->get($id, [
            'contain' => []
        ]);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $statistic = $this->Statistics->patchEntity($statistic, $this->request->getData());
            if ($this->Statistics->save($statistic)) {
                $this->Flash->success(__('The statistic has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The statistic could not be saved. Please, try again.'));
        }
        $this->set(compact('statistic'));
        $this->set('_serialize', ['statistic']);
    }

    /**
     * Delete method
     *
     * @param string|null $id Statistic id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $statistic = $this->Statistics->get($id);
        if ($this->Statistics->delete($statistic)) {
            $this->Flash->success(__('The statistic has been deleted.'));
        } else {
            $this->Flash->error(__('The statistic could not be deleted. Please, try again.'));
        }

        return $this->redirect(['action' => 'index']);
    }

	/*public function loadCostFromXml() {

		$data = $this->Import->prepareEntityData(TMP . 'stats_2.xlsx', ['worksheet'=> 0]);

		if(!empty($data)) {

			$statisticsTable = TableRegistry::get('StatisticsDaily');

			foreach($data as $campaignId => $campaign) {
				foreach($campaign as $date => $value) {
					if(is_numeric($date) && $value) {

						$dateFormat = date('Y-m-d', (($date-25569)*86400));

						$stat = $statisticsTable->newEntity();
						$stat['project_id'] = 1;
						$stat['campaign_id'] = $campaignId+1;
						$stat['cost'] = $value;
						$stat['date'] = $dateFormat;

						$statisticsTable->save($stat);
					}
				}

			}
		}

		var_dump($data);

		exit;
	}*/
}
