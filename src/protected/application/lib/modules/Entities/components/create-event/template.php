 <?php 
use MapasCulturais\i;
 
$this->import('
    create-occurrence
    entity-field 
    entity-terms
    modal 
'); 
?>

<modal title="Criar Evento" classes="create-modal" button-label="Criar Evento" @open="createEntity()" @close="destroyEntity()">
    <template v-if="entity" #default>
        <label><?php i::_e('Crie um evento com informações básicas')?><br><?php i::_e('e de forma rápida')?></label>
        <div class="create-modal__fields">
            <entity-field :entity="entity" hide-required label=<?php i::esc_attr_e("Nome ou título")?>  prop="name"></entity-field>
            <entity-terms :entity="entity" :editable="true" :classes="linguagemClasses" taxonomy='linguagem' title="<?php i::esc_attr_e("Linguagem cultural") ?>"></entity-terms>
            <small class="field__error" v-if="linguagemErrors">{{linguagemErrors.join(', ')}}</small>
            <entity-field :entity="entity" hide-required prop="shortDescription" label="<?php i::esc_attr_e("Adicione uma Descrição curta para o Evento")?>"></entity-field>
            <entity-field :entity="entity" hide-required v-for="field in fields" :prop="field"></entity-field>

        </div>
    </template>
    <template #button="modal">
        <slot :modal="modal"></slot>
    </template>
    <template #actions="modal">
        
        <modal title="Evento Criado">
            <template #default="modalSecond">
                <div class="label-modal">
                    <label><?php i::_e('Você pode competar as informações do seu evento ')?><br><?php i::_e('agora ou deixar para depois')?></label>
                </div>
            </template>
            <template #button="modalSecond">
                <button class="button button--primary" @click="modalSecond.open(); createPublic(modal)"><?php i::_e('Criar e Publicar')?></button>
            </template>
            <template #actions="modalSecond">
                <div class="create-modal__fields">
                    <a :href="entity.editUrl" class="button button--primary"><?php i::_e('Ver Evento')?></a>
                </div>
            </template>

        </modal>
        <button class="button button--solid-dark" @click="createDraft(modal)"><?php i::_e('Criar em Rascunho')?></button>
        <button class="button button--text button--text-del " @click="modal.close()"><?php i::_e('Cancelar')?></button>
        <create-occurrence></create-occurrence>
    </template>
</modal>