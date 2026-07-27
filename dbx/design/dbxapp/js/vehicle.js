/* -------------------------------------------------
 * dbx grid schema: vehicle
 * ------------------------------------------------- */
console.log('vehicle.js FINAL');

window.dbxGridSchema = {

    vehicle: {

        meta: {
            name: 'vehicle',
            version: '3.4',
            description: 'Vehicle grid schema clean cell scoped coloring'
        },

        /* =================================================
         * CONDITIONS
         * ================================================= */
        conditions: {

            /* --- TIPO (ROW COLOR) --- */
            isTLPM: { col:'TIPO', if:'==', value:'TL-PM' },
            isDKPM: { col:'TIPO', if:'==', value:'DK-PM' },
            isPM:   { col:'TIPO', if:'==', value:'PM' },
            isFG:   { col:'TIPO', if:'==', value:'FG' },
            isSM:   { col:'TIPO', if:'==', value:'SM' },
            isEK:   { col:'TIPO', if:'==', value:'EK' },
            isLB:   { col:'TIPO', if:'==', value:'LB' },
            isEXT:  { col:'TIPO', if:'==', value:'EXT' },
            isTL:   { col:'TIPO', if:'==', value:'TL' },
            isTLPB: { col:'TIPO', if:'==', value:'TL-PB' },
            isBAN:  { col:'TIPO', if:'==', value:'BAN' },
            isAST:  { col:'TIPO', if:'==', value:'AST' },
            isITBAS:{ col:'TIPO', if:'==', value:'IT-BAS' },
            isTAL:  { col:'TIPO', if:'==', value:'TAL' },

            /* --- CELL BASED COUNTRY DETECTION --- */
            isFrance: { col:'$cell', if:'startsWith', value:'F', normalize:'trim' },
            isSpain:  { col:'$cell', if:'startsWith', value:'E', normalize:'trim' }
        },

        /* =================================================
         * ROW RULES – Fahrzeugfarbe
         * ================================================= */
        rows: [
            { all:['isTLPM'], bg:'#F6E3A1', color:'#000' },
            { all:['isDKPM'], bg:'#F9E7B5', color:'#000' },
            { all:['isPM'],   bg:'#F4D35E', color:'#000' },
            { all:['isFG'],   bg:'#A8D5A2', color:'#000' },
            { all:['isSM'],   bg:'#E4EAF6', color:'#000' },
            { all:['isEK'],   bg:'#F6F6F6', color:'#000' },
            { all:['isLB'],   bg:'#AFC4E6', color:'#000' },
            { all:['isEXT'],  bg:'#D7EAD0', color:'#000' },
            { all:['isTL'],   bg:'#D9D9D9', color:'#000' },
            { all:['isTLPB'], bg:'#E3ECFA', color:'#000' },
            { all:['isBAN'],  bg:'#D3E2F4', color:'#000' },
            { all:['isAST'],  bg:'#C9DCF2', color:'#000' },
            { all:['isITBAS'],bg:'#B8CCE6', color:'#000' },
            { all:['isTAL'],  bg:'#F6D7B8', color:'#000' }
        ],

        /* =================================================
         * COLUMN RULES – Tagesplanung
         * ================================================= */
        columns: {

            /* ---------- d0 (Vergangenheit grau) ---------- */
            d0_origen_region:   { rules:[ {all:[],bg:'#E8E8E8',color:'#000'}, {all:['isFrance'],bg:'#A8D5A2'}, {all:['isSpain'],bg:'#F4D35E'} ] },
            d0_carga_region:    { rules:[ {all:[],bg:'#E8E8E8',color:'#000'}, {all:['isFrance'],bg:'#A8D5A2'}, {all:['isSpain'],bg:'#F4D35E'} ] },
            d0_origen_lugar:    { rules:[ {all:[],bg:'#E8E8E8',color:'#000'} ] },
            d0_carga_lugar:     { rules:[ {all:[],bg:'#E8E8E8',color:'#000'} ] },
            d0_observaciones:   { rules:[ {all:[],bg:'#E8E8E8',color:'#000'} ] },

            /* ---------- d1 (Vergangenheit grau) ---------- */
            d1_origen_region:   { rules:[ {all:[],bg:'#E8E8E8',color:'#000'}, {all:['isFrance'],bg:'#A8D5A2'}, {all:['isSpain'],bg:'#F4D35E'} ] },
            d1_carga_region:    { rules:[ {all:[],bg:'#E8E8E8',color:'#000'}, {all:['isFrance'],bg:'#A8D5A2'}, {all:['isSpain'],bg:'#F4D35E'} ] },
            d1_origen_lugar:    { rules:[ {all:[],bg:'#E8E8E8',color:'#000'} ] },
            d1_carga_lugar:     { rules:[ {all:[],bg:'#E8E8E8',color:'#000'} ] },
            d1_observaciones:   { rules:[ {all:[],bg:'#E8E8E8',color:'#000'} ] },

            /* ---------- d2 (Heute weiß) ---------- */
            d2_origen_region:   { rules:[ {all:[],bg:'#FFFFFF',color:'#000'}, {all:['isFrance'],bg:'#A8D5A2'}, {all:['isSpain'],bg:'#F4D35E'} ] },
            d2_carga_region:    { rules:[ {all:[],bg:'#FFFFFF',color:'#000'}, {all:['isFrance'],bg:'#A8D5A2'}, {all:['isSpain'],bg:'#F4D35E'} ] },
            d2_origen_lugar:    { rules:[ {all:[],bg:'#FFFFFF',color:'#000'} ] },
            d2_carga_lugar:     { rules:[ {all:[],bg:'#FFFFFF',color:'#000'} ] },
            d2_observaciones:   { rules:[ {all:[],bg:'#FFFFFF',color:'#000'} ] },

            /* ---------- d3 (Zukunft hellgelb) ---------- */
            d3_origen_region:   { rules:[ {all:[],bg:'#FFFBE6',color:'#000'}, {all:['isFrance'],bg:'#A8D5A2'}, {all:['isSpain'],bg:'#F4D35E'} ] },
            d3_carga_region:    { rules:[ {all:[],bg:'#FFFBE6',color:'#000'}, {all:['isFrance'],bg:'#A8D5A2'}, {all:['isSpain'],bg:'#F4D35E'} ] },
            d3_origen_lugar:    { rules:[ {all:[],bg:'#FFFBE6',color:'#000'} ] },
            d3_carga_lugar:     { rules:[ {all:[],bg:'#FFFBE6',color:'#000'} ] },
            d3_observaciones:   { rules:[ {all:[],bg:'#FFFBE6',color:'#000'} ] },

            /* ---------- d4 (Zukunft hellgelb) ---------- */
            d4_origen_region:   { rules:[ {all:[],bg:'#FFFBE6',color:'#000'}, {all:['isFrance'],bg:'#A8D5A2'}, {all:['isSpain'],bg:'#F4D35E'} ] },
            d4_carga_region:    { rules:[ {all:[],bg:'#FFFBE6',color:'#000'}, {all:['isFrance'],bg:'#A8D5A2'}, {all:['isSpain'],bg:'#F4D35E'} ] },
            d4_origen_lugar:    { rules:[ {all:[],bg:'#FFFBE6',color:'#000'} ] },
            d4_carga_lugar:     { rules:[ {all:[],bg:'#FFFBE6',color:'#000'} ] },
            d4_observaciones:   { rules:[ {all:[],bg:'#FFFBE6',color:'#000'} ] },

            /* ---------- d5 (Zukunft hellgelb) ---------- */
            d5_origen_region:   { rules:[ {all:[],bg:'#FFFBE6',color:'#000'}, {all:['isFrance'],bg:'#A8D5A2'}, {all:['isSpain'],bg:'#F4D35E'} ] },
            d5_carga_region:    { rules:[ {all:[],bg:'#FFFBE6',color:'#000'}, {all:['isFrance'],bg:'#A8D5A2'}, {all:['isSpain'],bg:'#F4D35E'} ] },
            d5_origen_lugar:    { rules:[ {all:[],bg:'#FFFBE6',color:'#000'} ] },
            d5_carga_lugar:     { rules:[ {all:[],bg:'#FFFBE6',color:'#000'} ] },
            d5_observaciones:   { rules:[ {all:[],bg:'#FFFBE6',color:'#000'} ] }
        }
    }
};

console.log(
    '### VEHICLE SCHEMA LOADED ###',
    Object.keys(window.dbxGridSchema.vehicle.columns)
);