<?php
namespace App\Controller\Api\User;

use App\Controller\Api\User\ApiController;
use Cake\Network\Exception\BadRequestException;
use Cake\Network\Exception\MethodNotAllowedException;
use Cake\Core\Exception\Exception;
use Cake\Network\Exception\NotFoundException;
use Cake\Network\Exception\UnauthorizedException;
use Cake\Log\Log;
use Cake\Collection\Collection;

/**
 * UserFavouriteExperts Controller
 *
 * @property \App\Model\Table\UserFavouriteExpertsTable $UserFavouriteExperts
 *
 * @method \App\Model\Entity\UserFavouriteExpert[] paginate($object = null, array $settings = [])
 */
class AppointmentBookingsController extends ApiController
{

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        //api for booking an appointment
        if(!$this->request->is(['post'])){
            throw new MethodNotAllowedException(__('BAD_REQUEST'));
        }
        
        $userId = $this->Auth->user('id');
      
        if(!$userId){
            throw new NotFoundException(__('We cant identify the user.'));
        }

        $data = $this->request->getData();

        if(!isset($data['stripeCardId']) || !$data['stripeCardId']){
            throw new MethodNotAllowedException(__('MANDATORY_FIELD_MISSING',"Stripe card id"));
        }
        if(!isset($data['expertId']) || !$data['expertId']){
            throw new MethodNotAllowedException(__('MANDATORY_FIELD_MISSING',"Expert id"));
        }

        if(!isset($data['availabilityId']) || !$data['availabilityId']){
            throw new MethodNotAllowedException(__('MANDATORY_FIELD_MISSING',"Expert Availability id"));

        }
        if(!isset($data['expSpecServiceId']) || !$data['expSpecServiceId']){
            throw new MethodNotAllowedException(__('MANDATORY_FIELD_MISSING',"Expert Specialization Service id"));
        }
        $this->loadModel('ExpertSpecializationServices');
        $expertSpecializationId = $this->ExpertSpecializationServices->findById($data['expSpecServiceId'])->first()->expert_specialization_id;

        if(!$expertSpecializationId){
            throw new NotFoundException(__('Expert Specialization id not found.'));
        }

        $this->loadModel('UserCards');
        $getCardDetails = $this->UserCards->findByUserId($userId)
                                    ->where(['stripe_card_id' => $data['stripeCardId']])
                                    ->first();

        if(!$getCardDetails){
            throw new NotFoundException(__('User Card details not found.'));
        }
        $userCardId = $getCardDetails->id;

        $data = [
                    'user_id' => $this->Auth->user('id'),
                    'expert_id' => $data['expertId'],
                    'expert_availability_id' => $data['availabilityId'],
                    'expert_specialization_id' => $expertSpecializationId,
                    'expert_specialization_service_id' => $data['expSpecServiceId'],
                    'user_card_id' => $userCardId
                ];
        $this->loadModel('Appointments');
        $bookingAppointment = $this->Appointments->newEntity();
        $bookingAppointment = $this->Appointments->patchEntity($bookingAppointment, $data);

        $expertsUserId = $this->Appointments->Experts->findById($data['expert_id'])->first()->user_id;
        Log::write('debug',$data); 
        if (!$this->Appointments->save($bookingAppointment,['user_id' =>$expertsUserId])) { 

          if($bookingAppointment->errors()){
            $this->_sendErrorResponse($bookingAppointment->errors());
          }
          throw new Exception("Error Processing Request");
        }
        
        $success = true;

        $this->set('data',$bookingAppointment);
        $this->set('status',$success);
        $this->set('_serialize', ['status','data']);
    }

    // List of Appointmnets on the basis of User or Expert and Filters
    public function index(){
        

        if(!$this->request->is(['get'])){
            throw new MethodNotAllowedException(__('BAD_REQUEST'));
        }

        $userId = $this->Auth->user('id');
        //check weather this user is an expert 
        $this->loadModel('Experts');
        $expert = $this->Experts->findByUserId($userId)->first();

        $this->loadModel('Appointments');
        
        if($expert){
            $reqData = $this->Appointments->findByExpertId($expert->id);
        }else{
            $reqData = $this->Appointments->findByUserId($userId);
        }
        
        $reqData = $reqData->contain(['ExpertSpecializationServices.SpecializationServices','ExpertSpecializations.Specializations','Transactions', 'ExpertAvailabilities', 'Experts.Users']);

        $filter = $this->request->query('filter');
        if($filter){

            switch ($filter) {
                case 'all':
                    $where = [''];
                    break;
                case 'pending':
                    $where = ['is_confirmed IS NULL','is_completed IS NULL'];
                    break;
                case 'rejected':
                    $where = ['is_confirmed' => 0,'is_completed IS NULL'];
                    break;
                case 'confirmed':
                    $where = ['is_confirmed' => 1,'is_completed IS NULL'];
                    break;
                case 'cancelled':
                    $where = ['is_confirmed' => 1,'is_completed' => 0];
                    break;
                case 'completed':
                    $where = ['is_confirmed' => 1,'is_completed' => 1];
                    break;
                
            }
        }

        $reqData = $reqData->where($where)->all()->toArray();

        $success = true;

        $this->set('data',$reqData);
        $this->set('status',$success);
        $this->set('_serialize', ['status','data']);
    }


}
