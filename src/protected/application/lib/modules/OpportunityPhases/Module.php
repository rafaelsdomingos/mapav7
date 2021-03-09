<?php
namespace OpportunityPhases;

use MapasCulturais\ApiQuery;
use MapasCulturais\App,
    MapasCulturais\i,
    MapasCulturais\Entities,
    MapasCulturais\Definitions,
    MapasCulturais\Exceptions;


class Module extends \MapasCulturais\Module{

    /**
     * Retorna o oportunidade principal
     *
     * @return \MapasCulturais\Entities\Opportunity
     */
    static function getBaseOpportunity(Entities\Opportunity $opportunity = null){
        if(is_null($opportunity)){
            $opportunity = self::getRequestedOpportunity();
        }

        if(!$opportunity){
            return null;
        }

        if($opportunity->isOpportunityPhase){
            $opportunity = $opportunity->parent;
        }

        return $opportunity;
    }

    /**
     * Retorna o oportunidade/fase que está sendo visualizado
     *
     * @return \MapasCulturais\Entities\Opportunity
     */
    static function getRequestedOpportunity(){
        $app = App::i();

        $opportunity = $app->view->controller->requestedEntity;

        if(!$opportunity){
            return null;
        }

        return $opportunity;
    }

    /**
     * Retorna a última fase do oportunidade
     *
     * @param \MapasCulturais\Entities\Opportunity $opportunity
     * @return \MapasCulturais\Entities\Opportunity
     */
    static function getLastCreatedPhase(Entities\Opportunity $opportunity) {
        $app = App::i();

        $base_opportunity = self::getBaseOpportunity($opportunity);

        $params = [
            '@select'=>'id',
            'parent' => "EQ({$base_opportunity->id})",
            'status' => 'GTE(-1)',
            '@permissions' => 'view',
            '@order' => 'registrationFrom DESC',
            '@limit' => 1
        ];

        $app->applyHook('entity(Opportunity).getLastCreatedPhase:params', [$base_opportunity, &$params]);

        $query = new ApiQuery(Entities\Opportunity::class, $params);

        if ($ids = $query->findIds()) {
            $last_phase = $app->repo('Opportunity')->find($ids[0]);
        } else {
            $last_phase = $base_opportunity;
        }

        return $last_phase;
    }

    /**
     * Retorna a última fase que teve seu período de inscrição terminado
     * @param \MapasCulturais\Entities\Opportunity $base_opportunity
     * @return \MapasCulturais\Entities\Opportunity
     */
    static function getLastCompletedPhase(Entities\Opportunity $base_opportunity){
        $now = new \DateTime;

        if($base_opportunity->registrationTo > $now){
            return null;
        }

        $result = $base_opportunity;
        $phases = self::getPhases($base_opportunity);

        foreach($phases as $phase){
            if($phase->registrationTo <= $now){
                $result = $phase;
            }
        }

        return $result;
    }

    /**
     * Retorna a fase atual
     * @param \MapasCulturais\Entities\Opportunity $base_opportunity
     * @return \MapasCulturais\Entities\Opportunity
     */
    static function getCurrentPhase(Entities\Opportunity $base_opportunity){
        $now = new \DateTime;

        $result = $base_opportunity;
        $phases = self::getPhases($base_opportunity);

        foreach($phases as $phase){
            if($phase->registrationTo > $now){
                continue;
            }
            $result = $phase;
        }

        return $result;
    }

    /**
     * Retorna a fase anterior a fase informada
     *
     * @param \MapasCulturais\Entities\Opportunity $phase
     * @return \MapasCulturais\Entities\Opportunity a fase anterior
     */
    static function getPreviousPhase(Entities\Opportunity $phase){
        if (!$phase->isOpportunityPhase) {
            return null;
        }

        $base_opportunity = self::getBaseOpportunity($phase);

        $phases = self::getPhases($base_opportunity);

        $result = $base_opportunity;

        foreach($phases as $p){
            if($p->createTimestamp < $phase->createTimestamp){
                $result = $p;
            }
        }

        return $result;
    }


    /**
     * Retorna as fases do oportunidade informado
     *
     * @param \MapasCulturais\Entities\Opportunity $opportunity
     * @return \MapasCulturais\Entities\Opportunity[]
     */
    static function getPhases(Entities\Opportunity $opportunity){
        if ($opportunity->canUser('@control')) {
            $status = [0,-1];
        } else {
            $status = -1;
        }

        $app = App::i();
        $phases = $app->repo('Opportunity')->findBy([
            'parent' => $opportunity,
            'status' => $status
        ],['registrationTo' => 'ASC', 'id' => 'ASC']);

        $phases = array_filter($phases, function($item) {
            if($item->isOpportunityPhase){
                return $item;
            }
        });

        return $phases;
    }

    static function getPreviousPhaseRegistration($registration){
        $app = App::i();
        $previous = null;

        if($prev_id = $registration->previousPhaseRegistrationId){
            $previous = $app->repo('Registration')->find($prev_id);
        }

        return $previous;
    }

    function _init () {
        $app = App::i();

        $registration_repository = $app->repo('Registration');

        $app->view->enqueueStyle('app', 'plugin-opportunity-phases', 'css/opportunity-phases.css');

        $app->hook('view.render(<<*>>):before', function() use($app) {
            $this->jsObject['angularAppDependencies'][] = 'OpportunityPhases';
            $app->view->enqueueScript('app', 'ng.opportunityPhases', 'js/ng.opportunityPhases.js', ['mapasculturais']);
        },1000);

        $app->hook('entity(Registration).get(previousPhase)', function(&$value) use($registration_repository) {
            if($this->previousPhaseRegistrationId) {
                $value = $registration_repository->find($this->previousPhaseRegistrationId);
            }

            if($value == $this) {
                $value = null;
            }
        });

        $app->hook('entity(Registration).get(nextPhase)', function(&$value) use($registration_repository) {
            if ($this->nextPhaseRegistrationId) {
                $value = $registration_repository->find($this->nextPhaseRegistrationId);
            }

            if($value == $this) {
                $value = null;
            }
        });

        $app->hook('entity(Registration).get(firstPhase)', function(&$value) use($registration_repository) {
            $opportunity = $this->opportunity;

            if ($opportunity->isOpportunityPhase) {
                $first_phase = Module::getBaseOpportunity($opportunity);
                $value = $registration_repository->findOneBy(['opportunity' => $first_phase, 'number' => $this->number]);
            }

            if($value == $this) {
                $value = null;
            }
        });

        $app->hook('entity(Registration).get(<<projectName|field_*>>)', function(&$value, $metadata_key) use($app) {
            if(!$value && $this->previousPhase) {
                $this->previousPhase->registerFieldsMetadata();
                
                $cache_id = "registration:{$this->number}:$metadata_key";
                if($app->cache->contains($cache_id)) {
                    $value = $app->cache->fetch($cache_id);
                } else {
                    $value = $this->previousPhase->$metadata_key;
                    $app->cache->save($cache_id, $value, DAY_IN_SECONDS);
                }
            }
        });


        $app->hook('entity(registration).meta(<<projectName|field_*>>).update:after', function() use($app) {
            $cache_id = "registration:{$this->owner->number}:{$this->key}";
            $app->cache->delete($cache_id);
        });

        // registra os metadados das inscrićões das fases anteriores
        $app->hook('<<GET|POST|PUT|PATCH|DELETE>>(registration.<<*>>):before', function() {
            $registration = $this->getRequestedEntity();


            if(!$registration || !$registration->id){
                return;
            }

            while($registration = self::getPreviousPhaseRegistration($registration)){
                $opportunity = $registration->opportunity;

                $this->registerRegistrationMetadata($opportunity);
            }
        });
        
        $app->hook('controller(opportunity).getSelectFields', function(Entities\Opportunity $opportunity, array &$fields) use($app) {
            while($opportunity = $opportunity->parent){
                foreach($opportunity->registrationFieldConfigurations as $field){
                    if($field->fieldType == 'select'){
                        if(!isset($fields[$field->fieldName])){
                            $fields[$field->fieldName] = $field;
                        }
                    }
                }
            }
        });

        $app->hook('view.partial(singles/registration-single--<<header|categories|agents>>).params', function(&$params, &$template) use ($app) {
            if($params['opportunity']->isOpportunityPhase){
                $entity = $params['entity'];
                while($entity = self::getPreviousPhaseRegistration($entity)){
                    $first = $entity;
                }

                $params['opportunity'] = $first->opportunity;
                $params['entity'] = $first;
            }
        });

        // unifica as fichas de inscricão
        $app->hook('template(registration.view.form):begin', function() use($app){
            $entity = $this->controller->requestedEntity;

            $current_registration = $entity;

            if($entity->status == Entities\Registration::STATUS_DRAFT){
                return;
            }

            $registrations = [$entity];

            $first = $entity;

            while($entity = self::getPreviousPhaseRegistration($entity)){
                $registrations[] = $entity;
                $first = $entity;
            }

            $registrations = array_reverse($registrations);
//
            $this->addEntityToJs($first);
            $this->addRegistrationToJs($first);
            $this->addOpportunityToJs($first->opportunity);

            $this->jsObject['evaluation'] = $this->getCurrentRegistrationEvaluation($current_registration);
            $this->jsObject['evaluationConfiguration'] = $current_registration->opportunity->evaluationMethodConfiguration;

            $this->jsObject['entity']['registrationFieldConfigurations'] = [];
            $this->jsObject['entity']['registrationFileConfigurations'] = [];

            foreach($registrations as $i => $reg){

                $opportunity = $reg->opportunity;

                if($opportunity->registrationFieldConfigurations->count() || $opportunity->registrationFileConfigurations->count()){
                    $empty = new Entities\RegistrationFieldConfiguration;
                    $empty->owner = $opportunity;
                    $empty->title = '';
                    $empty->fieldType = 'section';
                    $empty->displayOrder = $i * 1000 -2;


                    $section_divisor = new Entities\RegistrationFieldConfiguration;
                    $section_divisor->owner = $opportunity;
                    $section_divisor->fieldType = 'section';
                    $section_divisor->title = sprintf(i::__('%s - Inscrição %s'),$opportunity->name, $reg->id);
                    $section_divisor->displayOrder = $i * 1000 -1;

                    $this->jsObject['entity']['registrationFieldConfigurations'][] = $section_divisor;
                }

                foreach($opportunity->registrationFieldConfigurations as $field){
                    // faz um shift de 1000 * $i na ordem do campo
                    $field->displayOrder += $i * 1000;

                    $this->jsObject['entity']['registrationFieldConfigurations'][] = $field;

                    $field_name = $field->fieldName;
                    
                    $this->jsObject['entity']['object']->$field_name = $reg->$field_name;
                }

                foreach($opportunity->registrationFileConfigurations as $file){
                    // faz um shift de 1000 * $i na ordem do campo
                    $file->displayOrder += $i * 1000;

                    $this->jsObject['entity']['registrationFileConfigurations'][] = $file;
                }


            }

            $this->jsObject['entity']['id'] = $current_registration->id;
            $this->jsObject['entity']['object']->id = $current_registration->id;
            $this->jsObject['entity']['object']->opportunity = $current_registration->opportunity;

        });

        // action para criar uma nova fase no oportunidade
        $app->hook('POST(opportunity.createNextPhase)', function() use($app){
            $parent = $this->requestedEntity;

            $last_phase = self::getLastCreatedPhase($parent);

            if ($last_phase->isLastPhase) {
                $this->errorJson(i::__('Já foi criada a última fase!'), 400);
            }

            $_phases = [
                \MapasCulturais\i::__('Segunda fase'),
                \MapasCulturais\i::__('Terceira fase'),
                \MapasCulturais\i::__('Quarta fase'),
                \MapasCulturais\i::__('Quinta fase'),
                \MapasCulturais\i::__('Sexta fase'),
                \MapasCulturais\i::__('Sétima fase'),
                \MapasCulturais\i::__('Oitava fase'),
                \MapasCulturais\i::__('Nona fase'),
                \MapasCulturais\i::__('Décima fase')
            ];

            $phases = self::getPhases($parent);

            $num_phases = count($phases);

            $opportunity_class_name = $parent->getSpecializedClassName();

            $phase = new $opportunity_class_name;

            $phase->status = Entities\Opportunity::STATUS_DRAFT;
            $phase->parent = $parent;
            $phase->ownerEntity = $parent->ownerEntity;

            $phase->name = $_phases[$num_phases];
            $phase->registrationCategories = $parent->registrationCategories;
            $phase->shortDescription = sprintf(i::__('Descrição da %s'), $_phases[$num_phases]);
            $phase->type = $parent->type;
            $phase->owner = $parent->owner;
            $phase->useRegistrations = true;
            $phase->isOpportunityPhase = true;

            $_from = $last_phase->registrationTo ? clone $last_phase->registrationTo : new \DateTime;
            $_to = $last_phase->registrationTo ? clone $last_phase->registrationTo : new \DateTime;
            $_to->add(date_interval_create_from_date_string('1 days'));

            $phase->registrationFrom = $_from;
            $phase->registrationTo = $_to;

            if (isset($this->postData['isLastPhase']) && $this->postData['isLastPhase']) {
                $phase->isLastPhase = true;
            }
            
            $phase->save(true);

            $definition = $app->getRegisteredEvaluationMethodBySlug($this->data['evaluationMethod']);

            $emconfig = new Entities\EvaluationMethodConfiguration;

            $emconfig->opportunity = $phase;

            $emconfig->type = $definition->slug;

            $emconfig->save(true);

            $this->json($phase);
        });

        // redireciona para a página do oportunidade após deletar uma fase
        $app->hook('DELETE(opportunity):beforeRedirect', function($entity, &$redirect_url){
            if($entity->isOpportunityPhase){
                $redirect_url = $entity->parent->singleUrl;
            }
        });

        // adiciona o botão de importar inscrições da fase anterior
        $app->hook('view.partial(singles/opportunity-registrations--tables--manager):before', function(){
            if($this->controller->action === 'create'){
                return;
            }

            $opportunity = $this->controller->requestedEntity;

            if($opportunity->isOpportunityPhase){
                $this->part('import-last-phase-button', ['entity' => $opportunity]);
            }
        });

        // adiciona na ficha de inscrição das fases o link para a inscrição anterior
        $app->hook('view.partial(singles/registration-<<edit|single>>--header):before', function() use($app){
            $registration = $this->controller->requestedEntity;

            if($next_id = $registration->nextPhaseRegistrationId){
                $next_phase_registration = $app->repo('Registration')->find($next_id);
                if ($next_phase_registration) {
                    if($next_phase_registration->canUser('view')){
                        $this->part('next-phase-registration-link', ['next_phase_registration' => $next_phase_registration, 'registration' => $registration]);
                    }
                }
                
            }
        });

        // action para importar as inscrições da última fase concluida
        $app->hook('GET(opportunity.importLastPhaseRegistrations)', function() use($app) {
            ini_set('max_execution_time', 0);
            $target_opportunity = self::getRequestedOpportunity();

            $target_opportunity ->checkPermission('@control');

            $previous_phase = self::getPreviousPhase($target_opportunity);          

            $dql = "
                SELECT 
                    r1.id 
                FROM 
                    MapasCulturais\Entities\Registration r1
                WHERE 
                    r1.opportunity = :previous_opportunity AND
                    r1.status IN (10,8) AND
                    r1.number NOT IN (
                        SELECT 
                            r2.number 
                        FROM 
                            MapasCulturais\Entities\Registration r2 
                        WHERE 
                            r2.opportunity = :target_opportunity
                    )";

            $query = $app->em->createQuery($dql);

            $query->setParameters([
                'previous_opportunity' => $previous_phase,
                'target_opportunity' => $target_opportunity
            ]);

            $registration_ids = array_map(function($item){ return $item['id']; }, $query->getArrayResult());

            if(count($registration_ids) < 1){
                $this->errorJson(\MapasCulturais\i::__('Não há inscrições aprovadas fase anterior'), 400);
            }

            $new_registrations = [];

            $reg_repo = $app->repo('Registration');
            $opp_repo = $app->repo('Opportunity');

            $total = count($registration_ids);
            $count = 0;
            $app->disableAccessControl();
            foreach ($registration_ids as $registration_id){
                $count++;
                
                $r = $reg_repo->find($registration_id);

                $app->log->debug("({$count}/{$total}) Importando inscrição {$r->number} para a oportunidade {$target_opportunity->name} ({$target_opportunity->id})");
                
                $reg = new Entities\Registration;
                $reg->__skipQueuingPCacheRecreation = true;
                $reg->owner = $r->owner;
                $reg->opportunity = $opp_repo->find($target_opportunity->id);
                $reg->status = Entities\Registration::STATUS_DRAFT;
                $reg->number = $r->number;

                $reg->previousPhaseRegistrationId = $r->id;
                $reg->category = $r->category;
                $reg->save(true);

                if(isset($this->data['sent'])){
                    $reg->send();
                }
                $r->__skipQueuingPCacheRecreation = true;
                $r->nextPhaseRegistrationId = $reg->id;
                $r->save(true);

                $new_registrations[] = $reg;

                $app->em->clear();
            }

            $opp_repo->find($target_opportunity->id)->save(true);

            $app->enqueueEntityToPCacheRecreation($target_opportunity);
            $app->enableAccessControl();

            $this->finish($new_registrations);
        });

        // desliga a edição do campo principal de data quando vendo uma fase
        $app->hook('view.partial(singles/opportunity-about--registration-dates).params', function(&$params){
            $opportunity = self::getRequestedOpportunity();
            $base_opportunity = self::getBaseOpportunity();

            if(!$opportunity) {
                return;
            }

            if($opportunity->isOpportunityPhase){
                $params['entity'] = $base_opportunity;
                $params['disable_editable'] = true;
            }
        });

        // subsitui a mensagem de oportunidade rascunho quando for uma fase de oportunidade
        $app->hook('view.partial(singles/entity-status).params', function(&$params, &$template_name){
            $opportunity = self::getRequestedOpportunity();

            if(!$opportunity){
                return;
            }

            if($opportunity->isOpportunityPhase){
                $template_name = 'opportunity-phase-status';
            }
        });

        // muda o status de publicação dos oportunidades
        $app->hook('entity(<<*>>Opportunity).setStatus(1)', function(&$status) {
            if ($this->isOpportunityPhase) {
                $status = -1;
            }
        });

        // remove alguns campos da configuracao da oportunidade
        $app->hook('view.partial(singles/opportunity-registrations--<<fields--project-name|(agent|space)-relations|categories>>).params', function (&$params, &$template) use ($app) {
            $opportunity = self::getRequestedOpportunity();

            if($opportunity->isOpportunityPhase){
                $template = '_empty';
                return;
            }
        });

        // adiciona a lista e botão para criar novas fases
        $app->hook('view.partial(singles/widget-opportunities).params', function(&$params, &$template) use ($app){
            if($this->controller->action === 'create'){
                return;
            }

            $opportunity = self::getRequestedOpportunity();

            if($opportunity->isOpportunityPhase){
                $template = '_empty';
                return;
            }

            $params['opportunities'] = array_filter($params['opportunities'], function($e){
                if(! (bool) $e->isOpportunityPhase){
                    return $e;
                }
            });

            if($opportunity->isOpportunityPhase){
                $opportunity = $opportunity->parent;
            }

            if(!$opportunity->useRegistrations || !$opportunity->canUser('@control')){
                return;
            }

        });

        // remove form de fazer inscrição das fases
        $app->hook('view.partial(singles/opportunity-registrations--form).params', function(&$data, &$template){
            $opportunity = self::getRequestedOpportunity();

            if(!$opportunity){
                return;
            }

            if($opportunity->isOpportunityPhase){
                echo '<br>';
                $template = '_empty';
            }
        });

        // adiciona a lista de fases e o botão 'adicionar fase'
        $app->hook('template(opportunity.<<single|edit>>.tab-about--highlighted-message):end', function() use($app){
            $opportunity = self::getBaseOpportunity();

            $phases = self::getPhases($opportunity);

            $app->view->part('widget-opportunity-phases', ['opportunity' => $opportunity, 'phases' => $phases]);
        });


        // desabilita o modo de edição das partes abaixo
        $app->hook('view.partial(<<singles/type|entity-parent>>).params', function(&$data, &$template){
            $opportunity = $this->controller->requestedEntity;

            if(!$opportunity){
                return;
            }

            if($opportunity->isOpportunityPhase){
                $data['disable_editable'] = true;
            }
        });

        // remove a aba agenda de um oportunidade que é uma fase de outro oportunidade
        $app->hook('view.partial(<<agenda|singles/opportunity-events>>).params', function(&$data, &$template){
            $opportunity = $this->controller->requestedEntity;

            if(!$opportunity){
                return;
            }

            if($opportunity->isOpportunityPhase){
                $template = '_empty';
            }
        });

        // faz com que a fase seja acessível mes
        $app->hook('entity(Opportunity).canUser(view)', function($user, &$result){
            if($this->isOpportunityPhase && $this->status === -1){
                $result = true;
            }
        });

        $app->hook('entity(Registration).canUser(view)', function($user, &$result) use($app){
            if($result){
                return;
            }

            if($registration_id = $this->nextPhaseRegistrationId){
                $next_phase_registration = $app->repo('Registration')->find($registration_id);
                if ($next_phase_registration) {
                    $result = $next_phase_registration->canUser('view', $user);
                }                
            }
        });

        $app->hook('POST(registration.index):before', function() use($app) {
            $opportunity = $app->repo('Opportunity')->find($this->data['opportunityId']);

            if($opportunity->isOpportunityPhase){
                throw new Exceptions\PermissionDenied($app->user, $opportunity, 'register');
            }
        });
    }


    function register () {
        $app = App::i();

        $def__is_opportunity_phase = new Definitions\Metadata('isOpportunityPhase', ['label' => \MapasCulturais\i::__('Is a opportunity phase?')]);
        $def__previous_phase_imported = new Definitions\Metadata('previousPhaseRegistrationsImported', ['label' => \MapasCulturais\i::__('Previous phase registrations imported')]);

        $app->registerMetadata($def__is_opportunity_phase, 'MapasCulturais\Entities\Opportunity');
        $app->registerMetadata($def__previous_phase_imported, 'MapasCulturais\Entities\Opportunity');

        $def__prev = new Definitions\Metadata('previousPhaseRegistrationId', ['label' => \MapasCulturais\i::__('Previous phase registration id')]);
        $def__next = new Definitions\Metadata('nextPhaseRegistrationId', ['label' => \MapasCulturais\i::__('Next phase registration id')]);

        $app->registerMetadata($def__prev, 'MapasCulturais\Entities\Registration');
        $app->registerMetadata($def__next, 'MapasCulturais\Entities\Registration');

        // Last phase metadata
        $this->registerOpportunityMetadata('isLastPhase', [
            'label' => i::__('Indica se a oportunidade é a última fase da oportunidade'),
            'type' => 'boolean',
            'default' => false
        ]);
    }

}
