<?php
namespace PhpResumableUpload;

class ResumableUploadProcessor
{
  protected $_isComplete=false;
  
  public function __construct($config = [])
  {
    $this->config = array_merge(array(
        'processorId' => 'default',
        'parameterNameResumableIdentifier' => 'resumableIdentifier',
        'parameterNameResumableFilename' => 'resumableFilename',
        'parameterNameResumableChunkNumber' => 'resumableChunkNumber',
        'parameterNameResumableTotalChunks' => 'resumableTotalChunks',
        'parameterNameResumableTotalSize' => 'resumableTotalSize',
        'parameterNameFile' => 'file'
    ), $config);       
      
  }
  
  
  protected function getTotalChunks()
  {
    return intval($_REQUEST[ $this->config['parameterNameResumableTotalChunks'] ]);
  }

  protected function getChunkNumber()
  {
    return intval($_REQUEST[ $this->config['parameterNameResumableChunkNumber'] ]);
  }
  
  protected function getTotalSize()
  {
    return intval($_REQUEST[ $this->config['parameterNameResumableTotalSize'] ]);
  }

  protected function getIdentifier()
  {
    return $_REQUEST[ $this->config['parameterNameResumableIdentifier'] ];   
  }

  public function getOriginalFileName()
  {
    return $_REQUEST[ $this->config['parameterNameResumableFilename'] ];  
  }

  
  protected function getProcessorId()
  {
    return $this->config['processorId'];
  }

  protected function getFolderForChunkedUpload()
  {
    return sys_get_temp_dir().'/'.$this->getProcessorId()."---".md5($this->getIdentifier());
  }


  public function getTargetFileName()
  {
    return $this->getFolderForChunkedUpload().'/'.md5( $this->getOriginalFileName() );
  }

  protected function getBaseFileNameForChunks()
  {
    return $this->getTargetFileName().'.part';
  }

  protected function getFileNameForChunkOfCurrentRequest()
  {
    return $this->getBaseFileNameForChunks().$this->getChunkNumber();
  }

  protected function getFileNameForChunkWithNumber($chunkNumber)
  {
    return $this->getBaseFileNameForChunks().$chunkNumber;
  }

  protected function isChunkedUploadComplete()
  {
    $totalSizeAllParts = 0;
    
    for ($i=1; $i<=intval( $this->getTotalChunks() ); $i++) 
    {
      $partName = $this->getFileNameForChunkWithNumber($i);
      if (file_exists($partName))
      {
        $totalSizeAllParts += filesize($partName);
      }
    }
    
    if ($totalSizeAllParts === $this->getTotalSize() ) 
    {
      return true;
    }
    else 
    {
      return false;  
    }
  }
  
  protected function createCompleteFileFromChunks()
  {
    $fp = fopen($this->getTargetFileName(), 'w'); 
    for ($i=1; $i <= $this->getTotalChunks(); $i++) 
    {
      fwrite($fp, file_get_contents($this->getFileNameForChunkWithNumber($i)));
      error_log('writing chunk '.$i);
    }
    fclose($fp);
  }

  protected function removeFolderRecursively($dir) 
  {
    if (is_dir($dir)) 
    {
      $objects = scandir($dir);
      foreach ($objects as $object) 
      {
        if ($object != "." && $object != "..") 
        {
          $pathFile = $dir . "/" . $object;
          if (is_dir($pathFile)) 
          {
            $this->removeFolderRecursively($pathFile); 
          } 
          else 
          {
            unlink($pathFile);
          }
        }
      }
      reset($objects);
      rmdir($dir);
    }
  }


  public function existsChunk()
  {
    if ( $this->getTotalChunks() === $this->getChunkNumber() )
    {
       //we do want him to repeat the last chunk to initiate the copy-process.. something might have gon wrong before
       return false;
    }
    else if (file_exists($this->getFileNameForChunkOfCurrentRequest())) 
    {
      return true;
    } 
    else 
    {
      return false;
    }
      
  }
  
  public function processNextChunk()
  {
    $file = $_FILES[ $this->config['parameterNameFile'] ]; 
    
    if ($file['error'] === 1)
    {
      throw new \ErrorException('Error Uploading Chunk!');  
    }
    
    
    $temp_dir = $this->getFolderForChunkedUpload();
    if (!is_dir($temp_dir)) 
    {
      mkdir($temp_dir, 0777, true);
    }
    
    $dest_file = $this->getFileNameForChunkOfCurrentRequest();

    move_uploaded_file($file['tmp_name'], $dest_file);

    if ($this->isChunkedUploadComplete())
    {
      $this->createCompleteFileFromChunks();
      $this->_isComplete = true;
      return $this;
    }
    else 
    {
      $this->_isComplete = false;
      return $this;
    }
  }
  
  public function isComplete()
  {
    return $this->_isComplete;    
  }
  
  public function cleanUpAfterUpload()
  {
    $this->removeFolderRecursively( $this->getFolderForChunkedUpload() );
  }
  
  public function processAllChunks($finishedCallback)
  {
    $this->processNextChunk();
    if ($this->isComplete())
    {
      $finishedCallback($this->getTargetFileName());
    }
    $this->cleanUpAfterUpload();
  }
    
}
