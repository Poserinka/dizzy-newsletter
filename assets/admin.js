(()=>{
    document.addEventListener('click',event=>{
        const selectButton=event.target.closest('.dizzy-nl-select-hero');
        const removeButton=event.target.closest('.dizzy-nl-remove-hero');
        if(!selectButton&&!removeButton)return;
        event.preventDefault();
        const field=event.target.closest('.dizzy-nl-hero-field');
        if(!field)return;
        const input=field.querySelector('.dizzy-nl-hero-url');
        const preview=field.querySelector('.dizzy-nl-hero-preview');
        const image=preview.querySelector('img');
        const remove=field.querySelector('.dizzy-nl-remove-hero');
        if(removeButton){input.value='';image.removeAttribute('src');preview.hidden=true;remove.hidden=true;return;}
        const frame=wp.media({title:'Select campaign hero image',button:{text:'Use this image'},library:{type:'image'},multiple:false});
        frame.on('select',()=>{const attachment=frame.state().get('selection').first().toJSON();input.value=attachment.url||'';image.src=attachment.url||'';preview.hidden=!attachment.url;remove.hidden=!attachment.url;});
        frame.open();
    });
})();

