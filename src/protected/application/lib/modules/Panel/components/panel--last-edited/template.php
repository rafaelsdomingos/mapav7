<?php
use MapasCulturais\i;

$this->import('
    entity-card
');
?>

<div class="panel--last-edited">

    <div class="panel--last-edited__content">

        <div class="panel--last-edited__content-title">
            <label> <?php i::_e('Editados recentemente')?> </label>
        </div>

        <div class="panel--last-edited__content-cards">

            <carousel :settings="settings" :breakpoints="breakpoints">
                <slide v-for="entity in entities" :key="entity.id">

                    <panel--entity-card :key="entity.id" :entity="entity" class="card">

                        <template #title="{entity}">
                            <slot name="card-title" :entity="entity"></slot>
                        </template>

                        <template #header-actions="{entity}">
                            <div :class="[entity.__objectType+'__background', 'card-actions--tag']">
                                <mc-icon :name="entity.__objectType"></mc-icon>    
                                <span v-if="entity.__objectType=='agent'"> <?= i::_e('Agente') ?> </span>
                                <span v-if="entity.__objectType=='space'"> <?= i::_e('Espaço') ?> </span>
                                <span v-if="entity.__objectType=='event'"> <?= i::_e('Evento') ?> </span>
                                <span v-if="entity.__objectType=='project'"> <?= i::_e('Projeto') ?> </span>
                                <span v-if="entity.__objectType=='opportunity'"> <?= i::_e('Oportunidade') ?> </span>
                            </div>
                        </template>

                        <template #default="{entity}">
                            <span v-if="entity.shortDescription">
                               {{entity.shortDescription}}
                            </span>
                        </template>
                        
                        <template #entity-actions-left="{entity}">
                            &nbsp;
                        </template>
                        <template #entity-actions-center="{entity}">
                            &nbsp;
                        </template>

                    </panel--entity-card>

                </slide>                        

                <template #addons>
                    <div class="actions">
                        <navigation />
                    </div>
                </template>
            </carousel>
            
        </div>
    </div>
</div>