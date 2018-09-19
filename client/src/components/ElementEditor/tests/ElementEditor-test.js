/* eslint-disable import/no-extraneous-dependencies */
/* global jest, describe, it, expect */

import React from 'react';
import { Component as ElementEditor } from '../ElementEditor';
import Enzyme, { shallow } from 'enzyme';
import Adapter from 'enzyme-adapter-react-15.4/build/index';

Enzyme.configure({ adapter: new Adapter() });

describe('ElementEditor', () => {
  const ToolbarComponent = () => <div />;
  const ListComponent = () => <div className="elemental-editor__list" />;
  const testElementType = {
    name: 'TestElement',
    title: 'Test Block',
    icon: 'nothing',
    tabs: [
      { title: 'Content', name: 'Main' },
      { title: 'History', name: 'History' }
    ],
  };

  describe('render()', () => {
    it('should render ElementList and Toolbar', () => {
      const wrapper = shallow(
        <ElementEditor
          ToolbarComponent={ToolbarComponent}
          ListComponent={ListComponent}
          pageId={8}
          baseAddHref="#"
          elementTypes={[testElementType]}
        />
      );

      expect(wrapper.name()).toEqual('div');
      expect(wrapper.find(ListComponent)).toHaveLength(1);
      expect(wrapper.find(ToolbarComponent)).toHaveLength(1);
    });
  });
});
