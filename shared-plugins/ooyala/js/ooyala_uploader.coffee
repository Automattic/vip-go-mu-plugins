CHUNK_SIZE = 1024 * 1024 * 5 # 5MB
RETRY_INTERVAL = 1000 # 1 sec

class window.OoyalaUploader
  constructor: (options={})->
    @chunkProgress = {}
    @eventListeners = {}
    @initializeListeners(options)

  initializeListeners: (options) ->
    for eventType in ["embedCodeReady", "uploadProgress", "uploadComplete", "uploadError"]
      if options[eventType]?
        listeners = if options[eventType] instanceof Array then options[eventType] else [options[eventType]]
      else
        listeners = []
      @eventListeners[eventType] = listeners

  on: (eventType, eventListener) =>
    throw "invalid eventType" unless @eventListeners[eventType]?
    @eventListeners[eventType].push(eventListener)

  off: (eventType, eventListener=null) =>
    unless eventListener?
      @eventListeners[eventType] = []
      return
    listeners = @eventListeners[eventType]

    while (index = listeners.indexOf(eventListener)) >= 0
      listeners.splice(index, 1)

  uploadFile: (file, options={}) =>
    return false unless @browserSupported
    ooyalaUploader = new MovieUploader
      embedCodeReady: @embedCodeReady
      uploadProgress: @uploadProgress
      uploadComplete: @uploadComplete
      uploadError: @uploadError
    ooyalaUploader.uploadFile(file, options)
    true

  embedCodeReady: (assetID) =>
    for eventListener in (@eventListeners["embedCodeReady"] ? [])
      eventListener(assetID)

  uploadProgress: (assetID, progressPercent) =>
    previousProgress = @chunkProgress[assetID]
    return if progressPercent is previousProgress
    @chunkProgress[assetID] = progressPercent
    for eventListener in (@eventListeners["uploadProgress"] ? [])
      eventListener(assetID, progressPercent)

  uploadComplete: (assetID) =>
    delete @chunkProgress[assetID]
    for eventListener in (@eventListeners["uploadComplete"] ? [])
      eventListener(assetID)

  uploadError: (assetID, type, fileName, statusCode, message) =>
    for eventListener in (@eventListeners["uploadError"] ? [])
      eventListener(assetID, type, fileName, statusCode, message)

  browserSupported: FileReader?

class MovieUploader
  constructor: (options) ->
    @embedCodeReadyCallback = options?.embedCodeReady ? ->
    @uploadProgressCallback = options?.uploadProgress ? ->
    @uploadCompleteCallback = options?.uploadComplete ? ->
    @uploadErrorCallback = options?.uploadError ? ->
    @chunkUploaders = {}
    @completedChunkIndexes = []
    @completedChunks = 0
    @totalChunks

  ###
  Placeholders in the urls are replaced dynamically when the http request is built
  assetID   -   is replaced with the actual id of the asset (embed code)
  paths      -   is replaced with a comma separated list of labels, the ones that will be created
  ###
  uploadFile: (file, options) =>
    console.log("Uploading file using browser: #{navigator.userAgent}")
    @assetMetadata =
      assetCreationUrl: options.assetCreationUrl ? "/v2/assets"
      assetUploadingUrl: options.assetUploadingUrl ? "/v2/assets/assetID/uploading_urls"
      assetStatusUpdateUrl: options.assetStatusUpdateUrl ? "/v2/assets/assetID/upload_status"
      assetName: options.name ? file.name
      assetDescription : options.description ? ""
      assetType: options.assetType ? "video"
      fileSize: file.size
      createdAt: new Date().getTime()
      assetLabels: options.labels ? []
      postProcessingStatus: options.postProcessingStatus ? "live"
      labelCreationUrl: options.labelCreationUrl ? "/v2/labels/by_full_path/paths"
      labelAssignmentUrl: options.labelAssignmentUrl ? "/v2/assets/assetID/labels"
      assetID: ""
    @createAsset(file)

  createAsset: (file) =>
    $.ajax
      url: @assetMetadata.assetCreationUrl
      type: "POST"
      data:
        name: @assetMetadata.assetName
        description: @assetMetadata.assetDescription
        file_name: file.name
        file_size: @assetMetadata.fileSize
        asset_type: @assetMetadata.assetType
        chunk_size: CHUNK_SIZE
        post_processing_status: @assetMetadata.postProcessingStatus
      success: (response) => @onAssetCreated(file, response)
      error: (response) => @onError(response, "Asset creation error")

  onAssetCreated: (file, assetCreationResponse) =>
    parsedResponse = JSON.parse(assetCreationResponse)
    @assetMetadata.assetID = parsedResponse.embed_code
    ###
    Note: It could take some time for the asset to be copied. Send the upload ready callback
    immediately so that the user has some UI indication that upload has started
    ###
    @embedCodeReadyCallback(@assetMetadata.assetID)
    @assetMetadata.assetLabels.filter (arrayElement) -> arrayElement
    @createLabels() unless @assetMetadata.assetLabels.length is 0
    @getUploadingUrls(file)

  createLabels: ->
    listOfLabels = @assetMetadata.assetLabels.join(",")
    $.ajax
      url: @assetMetadata.labelCreationUrl.replace("paths", listOfLabels)
      type: "POST"
      success: (response) => @assignLabels(response)
      error: (response) => @onError(response, "Label creation error")

  assignLabels: (responseCreationLabels) ->
    parsedLabelsResponse = JSON.parse(responseCreationLabels)
    labelIds = (label["id"] for label in parsedLabelsResponse)
    $.ajax
      url: @assetMetadata.labelAssignmentUrl.replace("assetID", @assetMetadata.assetID)
      type: "POST"
      data: JSON.stringify(labelIds)
      success: (response) => @onLabelsAssigned(response)
      error: (response) => @onError(response, "Label assignment error")

  onLabelsAssigned: (responseAssignLabels) ->
    console.log("Creation and assignment of labels complete #{@assetMetadata.assetLabels}")

  getUploadingUrls: (file) ->
    $.ajax
      url: @assetMetadata.assetUploadingUrl.split("assetID").join(@assetMetadata.assetID)
      data:
        asset_id: @assetMetadata.assetID
      success: (response) =>
        @onUploadUrlsReceived(file, response)
      error: (response) =>
        @onError(response, "Error getting the uploading urls")

  ###
  Uploading all chunks
  ###
  onUploadUrlsReceived: (file, uploadingUrlsResponse) =>
    parsedUploadingUrl = JSON.parse(uploadingUrlsResponse)
    @totalChunks = parsedUploadingUrl.length
    chunks = new FileSplitter(file, CHUNK_SIZE).getChunks()

    if chunks.length isnt @totalChunks
      console.log("Sliced chunks (#{chunks.length}) and uploadingUrls (#{@totalChunks}) disagree.")

    $.each(chunks, (index, chunk) =>
      return if index in @completedChunkIndexes
      chunkUploader = new ChunkUploader
        assetMetadata: @assetMetadata
        chunkIndex: index
        chunk: chunk
        uploadUrl: parsedUploadingUrl[index]
        progress: @onChunkProgress
        completed: @onChunkComplete
        error: @uploadErrorCallback
      @chunkUploaders[index] = chunkUploader
      chunkUploader.startUpload()
    )

  progressPercent: ->
    bytesUploadedByInProgressChunks = 0
    for chunkIndex, chunkUploader of @chunkUploaders
      bytesUploadedByInProgressChunks += chunkUploader.bytesUploaded
    bytesUploaded = (@completedChunks * CHUNK_SIZE) + bytesUploadedByInProgressChunks
    uploadedPercent = Math.floor((bytesUploaded * 100) / @assetMetadata.fileSize)
    ### uploadedPercent can be more than 100 since the last chunk may be less than CHUNK_SIZE ###
    Math.min(100, uploadedPercent)

  onChunkProgress: =>
    @uploadProgressCallback(@assetMetadata.assetID, @progressPercent())

  onChunkComplete: (event, chunkIndex) =>
    @completedChunks++
    @completedChunkIndexes.push(chunkIndex)
    delete @chunkUploaders[chunkIndex]
    @onChunkProgress()
    @onAssetUploadComplete() if @completedChunks is @totalChunks

  onAssetUploadComplete: =>
    $.ajax
      url: @assetMetadata.assetStatusUpdateUrl.split("assetID").join(@assetMetadata.assetID)
      data:
        asset_id: @assetMetadata.assetID
        status: "uploaded"
      type: "PUT"
      success: (data) =>
        @uploadCompleteCallback(@assetMetadata.assetID)
      error: (response) =>
        @onError(response, "Setting asset status as uploaded error")

  onError: (response, clientMessage) =>
    try
      parsedResponse = JSON.parse(response.responseText)
      errorMessage = parsedResponse["message"]
    catch _
      errorMessage = response.statusText

    console.log("#{@assetMetadata.assetName}: #{clientMessage} with status #{response.status}: #{errorMessage}")
    @uploadErrorCallback
      assetID:     @assetMetadata.assetID
      type:         @assetMetadata.assetType
      fileName:     @assetMetadata.assetName
      statusCode:   response.status
      message:      "#{clientMessage}, #{errorMessage}"

class ChunkUploader
  constructor: (options) ->
    @assetMetadata = options.assetMetadata
    @chunk = options.chunk
    @chunkIndex = options.chunkIndex
    @progressHandler = options.progress
    @completedHandler = options.completed
    @uploadErrorCallback = options.error
    @uploadUrl = options.uploadUrl
    @bytesUploaded = 0

  startUpload: =>
    console.log("#{@assetMetadata.assetID}: Starting upload of chunk #{@chunkIndex}")
    @xhr = new XMLHttpRequest()
    @xhr.upload.addEventListener("progress", (event) =>
      @bytesUploaded = event.loaded
      @progressHandler()
    )
    @xhr.addEventListener("load", @onXhrLoad)
    @xhr.addEventListener("error", @onXhrError)
    @xhr.open("PUT", @uploadUrl)
    @xhr.send(@chunk)

  onXhrLoad: (xhr) =>
    status = xhr.target.status
    if status >= 400
      onXhrError(xhr)
    else
      @bytesUploaded = CHUNK_SIZE
      @completedHandler(xhr, @chunkIndex)

  ###
  The XHR error event is only fired if there's a failure at the network level. For application errors
  (e.g. The request returns a 404), the browser fires an onload event
  ###
  onXhrError: (xhr) =>
    status = xhr.target.status
    console.log("#{@assetMetadata.assetID}: chunk #{@chunkIndex}: Xhr Error Status #{status}")
    @uploadErrorCallback
      assetID:     @assetMetadata.assetID
      type:         @assetMetadata.assetType
      fileName:     @assetMetadata.assetName
      statusCode:   xhr.status
      message:      xhr.responseText

class FileSplitter
  constructor: (@file, @chunkSize) ->

  ###
  Splits the file into several pieces according to CHUNK_SIZE. Returns an array of chunks.
  ###
  getChunks: ->
    return [@file] unless @file.slice or @file.mozSlice
    @slice(i * @chunkSize, (i + 1) * @chunkSize) for i in [0...Math.ceil(@file.size/@chunkSize)]

  ###
  Gets a slice of the file. For example: consider a file of 100 bytes, slice(0,50) will give the first half
  of the file
  - start: index of the start byte
  - stop: index of the byte where the split should stop. If the stop is larger than the file size, stop will
  be the last byte.
  ###
  slice: (start, stop) ->
    if @file.slice
      @file.slice(start, stop)
    else if @file.mozSlice
      @file.mozSlice(start, stop)
