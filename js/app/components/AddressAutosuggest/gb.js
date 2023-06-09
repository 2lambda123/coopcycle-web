import React from 'react'
import axios from 'axios'
import ngeohash from 'ngeohash'
import _ from 'lodash'

import IdealPostcodes from './ideal-postcodes.svg'

window._paq = window._paq || []

const defaultFuseSearchOptions = {
  limit: 5
}

export function placeholder() {

  if (!this.state.postcode) {
    return this.props.t('ENTER_YOUR_POSTCODE')
  }

  return this.props.t('ENTER_YOUR_ADDRESS')
}

export function theme(theme) {

  if (this.state.postcode) {

    return {
      ...theme,
      input: `${theme.input} address-autosuggest__input--addon`
    }
  }

  return theme
}

const toAddress = (postcode) => ({
  latitude: postcode.latitude,
  longitude: postcode.longitude,
  geo: {
    latitude: postcode.latitude,
    longitude: postcode.longitude,
  },
  addressCountry: postcode.country,
  addressLocality: postcode.admin_district || '',
  addressRegion: postcode.region || '',
  postalCode: postcode.postcode,
  geohash: ngeohash.encode(postcode.latitude, postcode.longitude, 11),
  isPrecise: true,
  // streetAddress will be entered *manually*
})

const toPostcode = (address) => ({
  latitude: address.geo ? address.geo.latitude : address.latitude,
  longitude: address.geo ? address.geo.longitude : address.longitude,
  postcode: address.postalCode,
})

export function getInitialState() {

  let multiSection = false
  const suggestions = []

  if (this.props.addresses.length > 0) {

    multiSection = true

    const addressesAsSuggestions = this.props.addresses.map((address, idx) => ({
      type: 'address',
      value: address.streetAddress,
      address: {
        ...address,
        // Let's suppose saved addresses are precise
        isPrecise: true,
        needsGeocoding: false,
      },
      index: idx,
    }))

    suggestions.push({
      title: this.props.t('SAVED_ADDRESSES'),
      suggestions: addressesAsSuggestions.slice(0, 5),
    })
  }

  return {
    value: _.isObject(this.props.address) ?
      (this.props.address.streetAddress || '') : '',
    suggestions,
    multiSection,
    // @var object
    postcode: _.isObject(this.props.address) && this.props.address.postalCode ?
      toPostcode(this.props.address) : null
  }
}

export function onSuggestionsFetchRequested({ value }) {

  // @see http://postcodes.io/docs

  if (!this.state.postcode) {

    window._paq.push(['trackEvent', 'AddressAutosuggest', 'searchPostcode', value])

    let multiSection = false
    let suggestions = []

    if (this.props.addresses.length > 0) {

      const fuseResults = this.fuse.search(value, {
        ...defaultFuseSearchOptions,
        ...this.props.fuseSearchOptions,
      })

      if (fuseResults.length > 0) {

        const addressesAsSuggestions = fuseResults.map((fuseResult, idx) => ({
          type: 'address',
          value: fuseResult.item.streetAddress,
          address: fuseResult.item,
          index: idx,
        }))

        suggestions.push({
          title: this.props.t('SAVED_ADDRESSES'),
          suggestions: addressesAsSuggestions
        })
        multiSection = true
      }
    }

    axios({
      method: 'get',
      url: `https://api.postcodes.io/postcodes/${value.replace(/\s/g, '')}/autocomplete`,
    })
      .then(response => {

        let predictionsAsSuggestions = []

        if (response.data.status === 200 && Array.isArray(response.data.result)) {
          predictionsAsSuggestions = response.data.result.map(postcode => ({
            type: 'postcode',
            value: postcode,
            address: toAddress(postcode)
          }))
        }

        if (multiSection) {
          if (predictionsAsSuggestions.length > 0) {
            suggestions.push({
              title: this.props.t('ADDRESS_SUGGESTIONS'),
              suggestions: predictionsAsSuggestions,
            })
          }
        } else {
          suggestions = predictionsAsSuggestions
        }

        this.setState({
          suggestions,
          multiSection,
        })

      })
  } else {

    const parts = [ value ]
    if (this.state.postcode.admin_district) {
      parts.push(this.state.postcode.admin_district)
    }
    if (this.state.postcode.admin_county) {
      parts.push(this.state.postcode.admin_county)
    }
    parts.push(this.state.postcode.postcode)

    this.setState({
      multiSection: false,
      suggestions: [{
        type: 'manual_address',
        // https://ideal-postcodes.co.uk/guides/good-addressing-guidelines
        // https://postcoder.com/address-lookup
        // Ex: 10 Beaconsfield Street, York, North Yorkshire, YO24 4ND
        value: parts.join(', '),
      }],
    })
  }
}

export function onSuggestionSelected(event, { suggestion }) {

  // When country = gb
  // This does *NOT* trigger onAddressSelected
  if (suggestion.type === 'postcode') {
    axios({
      method: 'get',
      url: `https://api.postcodes.io/postcodes/${suggestion.value}`,
    })
      .then(response => {
        if (response.data.status === 200 && response.data.result) {
          this.setState({
            value: '',
            postcode: response.data.result,
          })
        }
      })
  }

  if (suggestion.type === 'manual_address') {

    const address = {
      ...toAddress(this.state.postcode),
      streetAddress: suggestion.value,
    }

    this.props.onAddressSelected(suggestion.value, address, suggestion.type)
  }

  if (suggestion.type === 'address') {
    this.setState({
      postcode: toPostcode(suggestion.address),
    })
    this.props.onAddressSelected(suggestion.value, suggestion.address, suggestion.type)
  }
}

export function poweredBy() {

  return (
    <img style={{ maxWidth: '130px' }} src={ IdealPostcodes } />
  )
}

export function highlightFirstSuggestion() {

  return true
}
