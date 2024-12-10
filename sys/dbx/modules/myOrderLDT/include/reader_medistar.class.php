<?php
namespace dbx\myOrderLDT;

Class reader_medistar {

    private function extract($lines,$ln,$word) {
        $value='';
        if (isset($lines[$ln])) {
           $line =$lines[$ln];
           $words=explode(';', $line);
           if (isset($words[$word])) $value=$words[$word];
           if ($word == 99) {
              $last = (count($words) -1);
              $value= $words[$last];
           }
        }
        return $value;
    }

    private function is_date($value) {
        $retval=0;
        if (strpos($value,'.')) {
            $ln=strlen($value);
            if ($ln == 8 || $ln == 10) $retval=1;
        }    
        return $retval;
    }



    public function get_lines($patient)  {     
        $xlines=array(); $ln=0;
        $patient= str_replace("\n\r","\n", $patient);
        $patient= str_replace("\r","\n"  , $patient);

        for ($i = 1; $i <= 5; $i++) {
            $patient= str_replace('  ', ' ', $patient);
        }
        

        $lines  = explode("\n", $patient);
        foreach ($lines as $no => $line) {
            $line= trim($line);
            $line= str_replace(' ',';',$line);
            if ($line > ' ') { 
                $xlines[$ln]=$line;
                $ln++;
            }    
        } 
        //dbx_debug("#Patient#----------------------------",$xlines);
        return $xlines;
    }    



    private function formate_date($value,$is_2000=0) {
        $retval  = date("Y-m-d");
        $current = date("Y");
        $xJ = substr($current, -2);
        if ($is_2000) $xJ=9999;
        
        $date=explode('.', $value);
        $count=count($date);
        if ($count==3) { 
            $tt=$date[0];
            $mm=$date[1];
            $yy=$date[2];
            if ($yy > $xJ) { 
                $yy=($yy + 1900);
            } else {
                $yy=($yy + 2000);
            }
            $retval=$yy.'-'.$mm.'-'.$tt;
        }
        return $retval;
    }


    public function get_data($fld,$lines,$default) {
        $value=$default;   

        switch ($fld) {


            case 'krankenkasse':
                $value=$this->extract($lines,0,0);
            break;    

            case 'nachname':
                $v0=$this->extract($lines,2,0);
                $v1=$this->extract($lines,2,1);
                $v2=$this->extract($lines,2,3);
                $v3=$this->extract($lines,2,4); 
                $value=$v0.' '.$v1.' '.$v2.' '.$v3;

            break; 
                
            case 'vorname':
                $v0=$this->extract($lines,3,0);
                $v1=$this->extract($lines,3,1);
                $v2=$this->extract($lines,3,2);
                $v3=$this->extract($lines,3,3); 
                              
                if ($this->is_date($v1)) $v1='';
                if ($this->is_date($v2)) $v2='';
                if ($this->is_date($v3)) $v3='';
                
                $value=$v0.$v1.$v2.$v3;
            break; 


            case 'gebdat':
                $v0=$this->extract($lines,3,0);
                $v1=$this->extract($lines,3,1);
                $v2=$this->extract($lines,3,2);
                $v3=$this->extract($lines,3,3); 
                
                if ($this->is_date($v1)) $value=$v1;
                if ($this->is_date($v2)) $value=$v2;
                if ($this->is_date($v3)) $value=$v3;

                //dbx_debug("#gebdat=($value)  ($v0|$v1|$v2|$v3)");


                $value=$this->formate_date($value);

                //dbx_debug("#gebdate format=($value)");

            break; 


            case 'strasse':
                $v0=$this->extract($lines,4,0);
                $v1=$this->extract($lines,4,1);
                $v2=$this->extract($lines,4,2);
                $v3=$this->extract($lines,4,3);
                $value=$v0.' '.$v1.' '.$v2.' '.$v3; 
            break;


            case 'lnd':
                $value=$this->extract($lines,5,0); 
            break;
            
            case 'plz':
                $value=$this->extract($lines,5,1); 
            break;


            case 'ort':
                $v1=$this->extract($lines,5,2);
                $v2=$this->extract($lines,5,3);
                $v3=$this->extract($lines,5,4);
                $value=$v1.' '.$v2.' '.$v3; 
            break;





            case 'geschlecht':
                $value='M';
                $v1=$this->extract($lines,5,99);
                $v1=strtoupper($v1);
                if ($v1 == 'M' || $v1 == 'W' || $v1= 'D') $value=$v1;
            break;

            case 'kostentraeger':
                $value=$this->extract($lines,6,0);
            break;   
                        
            case 'versicherungsnr':
                $value=$this->extract($lines,6,1);
            break;

            case 'status':
                $value= $this->extract($lines,6,2);
                $value = substr(str_pad($value, 4, "0", STR_PAD_RIGHT), 0, 4);
            break;


            case 'bsnr':
                $value=$this->extract($lines,7,0);
            break;

            case 'lanr':
                $value=$this->extract($lines,7,1);
            break;
    
            case 'abdatum':
                $val=$this->extract($lines,7,2);
                if ($this->is_date($val)) {
                   $value=$this->formate_date($val,1);
                } 
            break; 

            case 'abzeit':
                $value=date("H:i");
            break;    


            default:
            $value=$default;
     
        }
        $value=trim($value);
        $value= mb_convert_encoding($value, "UTF-8", "Windows-1252");
        //dbx_debug("get val of ($fld) default=($default) Value=($value)"); 

        return $value;
    }

}


?>